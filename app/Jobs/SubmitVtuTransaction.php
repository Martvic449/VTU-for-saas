<?php

namespace App\Jobs;

use App\Models\Provider;
use App\Services\Providers\ProviderAdapterRegistry;
use App\Services\Transactions\VtuTransactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/** Sequential multi-provider routing. Failover is allowed only after a definitive supplier failure. */
final class SubmitVtuTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 1;
    public int $timeout = 90;

    public function __construct(public readonly int $transactionId) {}

    public function handle(ProviderAdapterRegistry $adapters, VtuTransactionService $transactions): void
    {
        $tx = DB::table('transactions')->where('id', $this->transactionId)->first();
        if (! $tx || $tx->status !== 'reserved') return;
        $service = DB::table('service_catalogue')->where('id', $tx->service_catalogue_id)->first();
        if (! $service) return;
        $candidates = DB::table('provider_services')->join('providers', 'providers.id', '=', 'provider_services.provider_id')
            ->where('provider_services.service_catalogue_id', $tx->service_catalogue_id)
            ->where('provider_services.status', 'active')->where('providers.status', 'active')
            ->where('providers.environment', config('providers.environment', 'sandbox'))
            ->orderBy('provider_services.priority')->orderBy('provider_services.id')
            ->select(['provider_services.*', 'providers.code as provider_code'])->get();
        $candidates = $candidates->sortBy(fn ($row) => (int) $row->id === (int) $tx->provider_service_id ? 0 : 1)->values();
        $usedProviderIds = DB::table('transaction_attempts')->where('transaction_id', $tx->id)->pluck('provider_id')->map(fn ($id) => (int) $id)->all();
        $input = json_decode($tx->input_snapshot, true) ?: [];
        $lastFailure = 'All configured upstream suppliers confirmed failure.';

        foreach ($candidates as $index => $candidate) {
            if (in_array((int) $candidate->provider_id, $usedProviderIds, true)) continue;
            $provider = Provider::query()->where('id', $candidate->provider_id)->where('status', 'active')->where('environment', config('providers.environment', 'sandbox'))->first();
            if (! $provider) continue;
            $mapping = is_array($candidate->mapping) ? $candidate->mapping : (json_decode($candidate->mapping ?: '{}', true) ?: []);
            if ((int) $candidate->id !== (int) $tx->provider_service_id) {
                try { $transactions->prepareFallbackProvider($this->transactionId, (int) $candidate->provider_id, (int) $candidate->id, (string) $candidate->provider_product_code, $mapping); }
                catch (Throwable $exception) { report($exception); continue; }
                $tx = DB::table('transactions')->where('id', $this->transactionId)->first();
            }
            $payload = $input;
            if (isset($mapping['field_map']) && is_array($mapping['field_map'])) {
                $payload = [];
                foreach ($mapping['field_map'] as $target => $source) {
                    if (is_string($source) && array_key_exists($source, $input)) $payload[$target] = $input[$source];
                    elseif (! is_string($source)) $payload[$target] = $source;
                }
            }
            try {
                $transactions->markSubmitted($this->transactionId, (int) $provider->id);
                $result = $adapters->for($provider)->purchase((string) $service->service_code,
                    ['product_code' => $candidate->provider_product_code, '_provider_mapping' => $mapping] + $payload, (string) $tx->reference);
            } catch (Throwable $exception) {
                $transactions->markPending($this->transactionId);
                report($exception);
                return; // Unknown outcome: never switch suppliers.
            }

            DB::table('transaction_attempts')->where('transaction_id', $this->transactionId)->orderByDesc('attempt_number')->limit(1)->update([
                'provider_reference' => ($result['provider_reference'] ?? null) ?: null,
                'response_redacted' => json_encode(['outcome' => $result['outcome'] ?? 'pending', 'message' => $result['message'] ?? null,
                    'provider_status' => $result['data']['provider_status'] ?? null]), 'updated_at' => now()]);

            if (($result['outcome'] ?? 'pending') === 'successful') {
                $snapshot = json_decode($tx->price_snapshot, true) ?: [];
                try { $transactions->settleSuccessful($this->transactionId, (int) ($result['data']['actual_cost_minor'] ?? $snapshot['cost_minor'] ?? $tx->cost_minor)); }
                catch (Throwable $exception) { $transactions->markPending($this->transactionId, ($result['provider_reference'] ?? null) ?: null); report($exception); }
                return;
            }
            if (($result['outcome'] ?? 'pending') === 'pending') {
                $transactions->markPending($this->transactionId, ($result['provider_reference'] ?? null) ?: null);
                return; // Pending/timeout is not a failure; do not fail over.
            }
            $lastFailure = ($result['message'] ?? null) ?: 'Supplier confirmed transaction failure.';
            $transactions->markAttemptConfirmedFailure($this->transactionId, $lastFailure);
            $usedProviderIds[] = (int) $provider->id;
        }

        $current = DB::table('transactions')->where('id', $this->transactionId)->first();
        if ($current && $current->status === 'reserved' && DB::table('transaction_attempts')->where('transaction_id', $this->transactionId)->where('status', 'failed')->exists()) {
            $transactions->settleFailed($this->transactionId, $lastFailure);
        } else {
            DB::table('outbox_events')->insert(['event_uuid' => (string) Str::uuid(), 'event_type' => 'transaction.dispatch_blocked',
                'aggregate_type' => 'transaction', 'aggregate_id' => (string) $this->transactionId,
                'payload' => json_encode(['reference' => $tx->reference, 'reason' => 'No active provider mapping could be dispatched']),
                'status' => 'pending', 'attempts' => 0, 'available_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
