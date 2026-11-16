<?php

namespace App\Services\Transactions;

use App\Services\Accounting\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Services\Webhooks\WebhookEventDispatcher;
use RuntimeException;

/** Reserves prepaid credit atomically and settles/refunds only on definitive provider outcomes. */
final class VtuTransactionService
{
    public function __construct(private readonly LedgerService $ledger, private readonly WebhookEventDispatcher $webhooks) {}

    public function reserve(int $walletId, int $serviceId, string $idempotencyKey, array $input, ?int $tenantId = null): array
    {
        return DB::transaction(function () use ($walletId, $serviceId, $idempotencyKey, $input, $tenantId): array {
            $wallet = DB::table('wallets')->where('id', $walletId)->lockForUpdate()->first();
            if (! $wallet || $wallet->status !== 'active') throw new RuntimeException('Wallet is unavailable.');
            if ($tenantId === null && $wallet->owner_type !== 'user') throw new RuntimeException("Direct customer transactions must use the customer's own wallet.");
            if ($tenantId !== null && (! in_array($wallet->owner_type, ['tenant', 'tenant_customer'], true) || (int) $wallet->tenant_id !== $tenantId)) throw new RuntimeException('Wallet does not belong to the active tenant.') ;

            $fingerprint = hash('sha256', json_encode(['service_id' => $serviceId, 'input' => $input], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $existing = DB::table('transactions')->where('wallet_id', $walletId)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if (! hash_equals($existing->request_fingerprint, $fingerprint)) throw new RuntimeException('Idempotency key was already used with a different request payload.');
                return (array) $existing;
            }

            $service = DB::table('service_catalogue')->where('id', $serviceId)->where('status', 'active')->first();
            if (! $service) throw new RuntimeException('Service is unavailable.');
            $schema = json_decode($service->validation_schema ?: '{}', true) ?: [];
            if (! $schema) throw new RuntimeException('Service validation rules are not configured.');
            Validator::make($input, $schema)->validate();
            $providerMapping = DB::table('provider_services')->join('providers', 'providers.id', '=', 'provider_services.provider_id')
                ->where('provider_services.service_catalogue_id', $serviceId)
                ->where('provider_services.status', 'active')->where('providers.status', 'active')
                ->where('providers.environment', config('providers.environment', 'sandbox'))
                ->orderBy('provider_services.priority')->orderBy('provider_services.id')
                ->select(['provider_services.id as provider_service_id', 'provider_services.provider_id', 'provider_services.provider_product_code', 'provider_services.mapping'])
                ->first();
            if (! $providerMapping) throw new RuntimeException('No active upstream provider mapping is configured for this service and execution environment.');
            $activePrices = DB::table('pricing_rules')->where('service_catalogue_id', $serviceId)
                ->where(function ($q) use ($providerMapping) { $q->whereNull('provider_service_id')->orWhere('provider_service_id', $providerMapping->provider_service_id); })
                ->where('status', 'active')->where('effective_from', '<=', now())
                ->where(function ($q) { $q->whereNull('effective_until')->orWhere('effective_until', '>', now()); });
            $platformPricing = (clone $activePrices)->whereNull('tenant_id')->orderByRaw('CASE WHEN provider_service_id IS NULL THEN 1 ELSE 0 END')->orderByDesc('effective_from')->first();
            $tenantPricing = $tenantId ? (clone $activePrices)->where('tenant_id', $tenantId)->orderByRaw('CASE WHEN provider_service_id IS NULL THEN 1 ELSE 0 END')->orderByDesc('effective_from')->first() : null;
            $pricing = $tenantPricing ?: $platformPricing;
            if (! $pricing || ! $platformPricing) throw new RuntimeException('No active platform price is configured for this service.');
            $sell = (int) $pricing->sell_minor;
            $cost = (int) $platformPricing->cost_minor;
            if ($tenantPricing) {
                if (($platformPricing->floor_minor !== null && $sell < (int) $platformPricing->floor_minor)
                    || ($platformPricing->ceiling_minor !== null && $sell > (int) $platformPricing->ceiling_minor)) {
                    throw new RuntimeException('Tenant sell price is outside the platform price floor or ceiling.');
                }
            }
            if ($sell <= 0 || $cost < 0 || $cost > $sell || $pricing->currency !== $wallet->currency || $platformPricing->currency !== $wallet->currency) throw new RuntimeException('The configured price is invalid for this wallet.');

            $walletMapping = DB::table('wallet_ledger_accounts')->where('wallet_id', $walletId)->first();
            $suspense = DB::table('ledger_accounts')->where('code', 'PLATFORM_SUSPENSE_NGN')->first();
            if (! $walletMapping || ! $suspense) throw new RuntimeException('Wallet or suspense ledger account is not configured.');
            $walletAccount = DB::table('ledger_accounts')->where('id', $walletMapping->ledger_account_id)->lockForUpdate()->first();
            if (! $walletAccount) throw new RuntimeException('Wallet ledger account is missing.');
            $credits = (int) DB::table('ledger_entries')->where('ledger_account_id', $walletAccount->id)->where('side', 'credit')->sum('amount_minor');
            $debits = (int) DB::table('ledger_entries')->where('ledger_account_id', $walletAccount->id)->where('side', 'debit')->sum('amount_minor');
            if (($credits - $debits) < $sell) throw new RuntimeException('Insufficient wallet balance.');

            $reference = now('Africa/Lagos')->format('YmdHi').Str::upper(Str::random(16));
            $transactionId = DB::table('transactions')->insertGetId([
                'tenant_id' => $tenantId, 'wallet_id' => $walletId, 'service_catalogue_id' => $serviceId,
                'provider_id' => (int) $providerMapping->provider_id, 'provider_service_id' => (int) $providerMapping->provider_service_id,
                'provider_product_code' => $providerMapping->provider_product_code,
                'reference' => $reference, 'idempotency_key' => $idempotencyKey, 'request_fingerprint' => $fingerprint, 'status' => 'validated',
                'sell_minor' => $sell, 'cost_minor' => $cost, 'currency' => $wallet->currency,
                'input_snapshot' => json_encode($input),
                'price_snapshot' => json_encode(['sell_minor' => $sell, 'cost_minor' => $cost, 'currency' => $wallet->currency, 'platform_price_rule_id' => $platformPricing->id, 'tenant_price_rule_id' => $tenantPricing?->id, 'floor_minor' => $platformPricing->floor_minor, 'ceiling_minor' => $platformPricing->ceiling_minor,
                    'provider_id' => (int) $providerMapping->provider_id, 'provider_service_id' => (int) $providerMapping->provider_service_id,
                    'provider_product_code' => $providerMapping->provider_product_code,
                    'provider_mapping' => json_decode($providerMapping->mapping ?: '{}', true) ?: []]),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $journalId = $this->ledger->post('vtu.reserve', $wallet->currency, [
                ['account_id' => (int) $walletAccount->id, 'side' => 'debit', 'amount_minor' => $sell],
                ['account_id' => (int) $suspense->id, 'side' => 'credit', 'amount_minor' => $sell],
            ], 'RES-'.$reference, null, 'Reserve service purchase credit');

            DB::table('transactions')->where('id', $transactionId)->update([
                'status' => 'reserved', 'ledger_journal_id' => $journalId, 'updated_at' => now(),
            ]);
            return (array) DB::table('transactions')->where('id', $transactionId)->first();
        }, 3);
    }

    public function markSubmitted(int $transactionId, int $providerId, ?string $providerReference = null): void
    {
        DB::transaction(function () use ($transactionId, $providerId, $providerReference): void {
            $tx = DB::table('transactions')->where('id', $transactionId)->lockForUpdate()->first();
            if (! $tx || $tx->status !== 'reserved') throw new RuntimeException('Only reserved transactions can be submitted.');
            $provider = DB::table('providers')->where('id', $providerId)->where('status', 'active')->where('environment', config('providers.environment', 'sandbox'))->first();
            $providerMapping = DB::table('provider_services')->where('id', $tx->provider_service_id)->where('provider_id', $providerId)
                ->where('service_catalogue_id', $tx->service_catalogue_id)->where('status', 'active')->exists();
            if (! $provider || ! $providerMapping || (int) $tx->provider_id !== $providerId) throw new RuntimeException('The snapshotted provider or mapping is no longer active.');
            $attempt = (int) DB::table('transaction_attempts')->where('transaction_id', $transactionId)->max('attempt_number') + 1;
            DB::table('transaction_attempts')->insert([
                'transaction_id' => $transactionId, 'provider_id' => $providerId, 'attempt_number' => $attempt,
                'status' => 'submitted', 'provider_reference' => $providerReference,
                'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('transactions')->where('id', $transactionId)->update([
                'status' => 'submitted', 'provider_reference' => $providerReference, 'submitted_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }

    public function prepareFallbackProvider(int $transactionId, int $providerId, int $providerServiceId, string $productCode, array $mapping): void
    {
        DB::transaction(function () use ($transactionId, $providerId, $providerServiceId, $productCode, $mapping): void {
            $tx = DB::table('transactions')->where('id', $transactionId)->lockForUpdate()->first();
            if (! $tx || $tx->status !== 'reserved') throw new RuntimeException('Only a reserved transaction can switch to a fallback provider.');
            $last = DB::table('transaction_attempts')->where('transaction_id', $transactionId)->orderByDesc('attempt_number')->first();
            if (! $last || $last->status !== 'failed') throw new RuntimeException('Fallback is allowed only after a provider has definitively confirmed failure.');
            $alreadyUsed = DB::table('transaction_attempts')->where('transaction_id', $transactionId)->where('provider_id', $providerId)->exists();
            if ($alreadyUsed) throw new RuntimeException('A provider that has already been attempted cannot be retried automatically.');
            $provider = DB::table('providers')->where('id', $providerId)->where('status', 'active')->where('environment', config('providers.environment', 'sandbox'))->first();
            $service = DB::table('provider_services')->where('id', $providerServiceId)->where('provider_id', $providerId)->where('service_catalogue_id', $tx->service_catalogue_id)->where('status', 'active')->first();
            if (! $provider || ! $service) throw new RuntimeException('Fallback provider or service mapping is no longer active.');
            $snapshot = json_decode($tx->price_snapshot, true) ?: [];
            $history = $snapshot['provider_history'] ?? [];
            $history[] = ['provider_id' => (int) $tx->provider_id, 'provider_service_id' => (int) $tx->provider_service_id, 'provider_product_code' => $tx->provider_product_code, 'outcome' => 'confirmed_failure'];
            $snapshot['provider_history'] = $history;
            $snapshot['provider_id'] = $providerId;
            $snapshot['provider_service_id'] = $providerServiceId;
            $snapshot['provider_product_code'] = $productCode;
            $snapshot['provider_mapping'] = $mapping;
            $providerPrice = DB::table('pricing_rules')->where('service_catalogue_id', $tx->service_catalogue_id)
                ->whereNull('tenant_id')->where('status', 'active')->where('effective_from', '<=', now())
                ->where(function ($q) { $q->whereNull('effective_until')->orWhere('effective_until', '>', now()); })
                ->where(function ($q) use ($providerServiceId) { $q->where('provider_service_id', $providerServiceId)->orWhereNull('provider_service_id'); })
                ->orderByRaw('CASE WHEN provider_service_id IS NULL THEN 1 ELSE 0 END')->orderByDesc('effective_from')->first();
            if (! $providerPrice || (int) $providerPrice->cost_minor > (int) $tx->sell_minor) throw new RuntimeException('Fallback supplier has no valid cost rule below the transaction sell price.');
            $snapshot['cost_minor'] = (int) $providerPrice->cost_minor;
            $snapshot['provider_cost_rule_id'] = (int) $providerPrice->id;
            DB::table('transactions')->where('id', $transactionId)->update([
                'provider_id' => $providerId, 'provider_service_id' => $providerServiceId,
                'provider_product_code' => $productCode, 'cost_minor' => (int) $providerPrice->cost_minor,
                'price_snapshot' => json_encode($snapshot), 'updated_at' => now(),
            ]);
        }, 3);
    }

    public function markAttemptConfirmedFailure(int $transactionId, string $reason): void
    {
        DB::transaction(function () use ($transactionId, $reason): void {
            $tx = DB::table('transactions')->where('id', $transactionId)->lockForUpdate()->first();
            if (! $tx || ! in_array($tx->status, ['submitted', 'pending'], true)) throw new RuntimeException('Only a submitted or pending transaction can record a confirmed provider failure.');
            $attempt = DB::table('transaction_attempts')->where('transaction_id', $transactionId)->orderByDesc('attempt_number')->lockForUpdate()->first();
            if (! $attempt || ! in_array($attempt->status, ['submitted', 'pending'], true)) throw new RuntimeException('No open provider attempt exists.');
            DB::table('transaction_attempts')->where('id', $attempt->id)->update(['status' => 'failed', 'error_message' => $reason, 'finished_at' => now(), 'updated_at' => now()]);
            DB::table('transactions')->where('id', $transactionId)->update(['status' => 'reserved', 'updated_at' => now()]);
        }, 3);
    }

    public function markPending(int $transactionId, ?string $providerReference = null): void
    {
        DB::transaction(function () use ($transactionId, $providerReference): void {
            $tx = DB::table('transactions')->where('id', $transactionId)->lockForUpdate()->first();
            if (! $tx || ! in_array($tx->status, ['submitted', 'pending'], true)) throw new RuntimeException('Only submitted transactions can become pending.');
            DB::table('transactions')->where('id', $transactionId)->update([
                'status' => 'pending', 'provider_reference' => $providerReference ?? $tx->provider_reference, 'updated_at' => now(),
            ]);
            if ($tx->status !== 'pending') {
                $this->webhooks->dispatch('transaction.pending', ['reference' => $tx->reference, 'status' => 'pending'], $tx->tenant_id ? (int) $tx->tenant_id : null);
            }
            DB::table('transaction_attempts')->where('transaction_id', $transactionId)->where('attempt_number', DB::table('transaction_attempts')->where('transaction_id', $transactionId)->max('attempt_number'))->update(['status' => 'pending', 'updated_at' => now()]);
        }, 3);
    }

    public function settleSuccessful(int $transactionId, int $actualCostMinor): void
    {
        DB::transaction(function () use ($transactionId, $actualCostMinor): void {
            $tx = DB::table('transactions')->where('id', $transactionId)->lockForUpdate()->first();
            if (! $tx || ! in_array($tx->status, ['submitted', 'pending'], true)) throw new RuntimeException('Only submitted or pending transactions can be settled.');
            if ($actualCostMinor < 0 || $actualCostMinor > (int) $tx->sell_minor) throw new RuntimeException('Provider cost is outside the permitted transaction amount.');
            $suspense = DB::table('ledger_accounts')->where('code', 'PLATFORM_SUSPENSE_NGN')->first();
            $payable = DB::table('ledger_accounts')->where('code', 'PROVIDER_PAYABLE_NGN')->first();
            $revenue = DB::table('ledger_accounts')->where('code', 'SERVICE_REVENUE_NGN')->first();
            $providerCost = DB::table('ledger_accounts')->where('code', 'PROVIDER_COST_NGN')->first();
            if (! $suspense || ! $payable || ! $revenue || ! $providerCost) throw new RuntimeException('Settlement accounts are not configured.');
            $lines = [
                ['account_id' => (int) $suspense->id, 'side' => 'debit', 'amount_minor' => (int) $tx->sell_minor],
                ['account_id' => (int) $revenue->id, 'side' => 'credit', 'amount_minor' => (int) $tx->sell_minor],
            ];
            if ($actualCostMinor > 0) {
                $lines[] = ['account_id' => (int) $providerCost->id, 'side' => 'debit', 'amount_minor' => $actualCostMinor];
                $lines[] = ['account_id' => (int) $payable->id, 'side' => 'credit', 'amount_minor' => $actualCostMinor];
            }
            $journalId = $this->ledger->post('vtu.settlement', $tx->currency, $lines, 'SET-'.$tx->reference, null, 'Successful VTU transaction settlement');
            DB::table('transactions')->where('id', $transactionId)->update([
                'status' => 'successful', 'cost_minor' => $actualCostMinor, 'settlement_journal_id' => $journalId,
                'completed_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('transaction_attempts')->where('transaction_id', $transactionId)->where('attempt_number', DB::table('transaction_attempts')->where('transaction_id', $transactionId)->max('attempt_number'))->update(['status' => 'successful', 'finished_at' => now(), 'updated_at' => now()]);
            $this->webhooks->dispatch('transaction.successful', ['reference' => $tx->reference, 'status' => 'successful', 'amount_minor' => (int) $tx->sell_minor, 'currency' => $tx->currency], $tx->tenant_id ? (int) $tx->tenant_id : null);
        }, 3);
    }

    public function settleFailed(int $transactionId, string $reason): void
    {
        DB::transaction(function () use ($transactionId, $reason): void {
            $tx = DB::table('transactions')->where('id', $transactionId)->lockForUpdate()->first();
            if (! $tx || ! in_array($tx->status, ['submitted', 'pending', 'reserved'], true)) throw new RuntimeException('Only submitted, pending or definitively failed reserved transactions can be refunded.');
            if ($tx->status === 'reserved' && ! DB::table('transaction_attempts')->where('transaction_id', $transactionId)->where('status', 'failed')->exists()) throw new RuntimeException('A reserved transaction can be refunded only after a confirmed provider failure.');
            if (! $tx->ledger_journal_id) throw new RuntimeException('Original wallet reservation is missing.');
            $journalId = $this->ledger->reverse((int) $tx->ledger_journal_id, 'REF-'.$tx->reference, null, $reason);
            DB::table('transactions')->where('id', $transactionId)->update([
                'status' => 'failed', 'settlement_journal_id' => $journalId, 'failure_reason' => $reason,
                'completed_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('transaction_attempts')->where('transaction_id', $transactionId)->where('attempt_number', DB::table('transaction_attempts')->where('transaction_id', $transactionId)->max('attempt_number'))->update(['status' => 'failed', 'error_message' => $reason, 'finished_at' => now(), 'updated_at' => now()]);
            $this->webhooks->dispatch('transaction.failed', ['reference' => $tx->reference, 'status' => 'failed', 'reason' => $reason], $tx->tenant_id ? (int) $tx->tenant_id : null);
        }, 3);
    }
}
