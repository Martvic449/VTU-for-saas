<?php
namespace App\Http\Controllers;

use App\Services\Payments\DepositPostingService;
use App\Services\Payments\PaymentGatewayRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class DepositController
{
    public function store(Request $request, PaymentGatewayRegistry $gateways, \App\Services\Subscriptions\SubscriptionEntitlementService $entitlements): RedirectResponse
    {
        $enabled = $gateways->enabled();
        if ($enabled === []) return back()->withErrors(['deposit' => 'No payment gateway is configured. Please contact the platform administrator.']);
        $data = $request->validate(['amount' => ['required', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/'], 'gateway' => ['required', 'string', 'in:'.implode(',', $enabled)]]);
        $parts = explode('.', (string) $data['amount'], 2);
        $amountMinor = ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '0', 2, '0');
        abort_if($amountMinor < 50000 || $amountMinor > 500000000, 422, 'Deposit amount must be between ₦500 and ₦5,000,000.');
        $tenant = $request->attributes->get('tenant');
        if (! $tenant && $request->user()->hasRole('tenant-owner')) {
            $host = strtolower(rtrim($request->getHost(), '.'));
            $platformHosts = config('tenancy.platform_hosts', []);
            $base = strtolower((string) config('tenancy.base_domain'));
            if ($base !== '') $platformHosts[] = $base;
            if (in_array($host, $platformHosts, true)) {
                $owned = $request->user()->tenants()
                    ->where('tenants.status', 'active')
                    ->wherePivot('status', 'active')
                    ->wherePivot('role', 'tenant-owner')
                    ->get();
                abort_unless($owned->count() === 1, 409, 'Your approved reseller workspace could not be identified.');
                $tenant = $owned->first();
                $request->attributes->set('tenant', $tenant);
            }
        }
        if ($tenant) {
            abort_unless($tenant->status === 'active' && $entitlements->allows((int) $tenant->id, 'vtu_enabled'), 402, 'This reseller plan does not currently permit customer wallet funding or purchases.');
            $isTenantMember = $request->user()->tenants()->whereKey($tenant->id)->wherePivot('status', 'active')->exists();
            if ($isTenantMember) {
                $wallet = DB::table('wallets')->where('owner_type', 'tenant')->where('owner_id', $tenant->id)->where('tenant_id', $tenant->id)->where('currency', 'NGN')->where('status', 'active')->first();
            } else {
                $tenantCustomer = DB::table('tenant_customers')->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->where('status', 'active')->first();
                abort_unless($tenantCustomer, 403, 'You do not have an active customer account with this reseller.');
                $wallet = DB::table('wallets')->where('owner_type', 'tenant_customer')->where('owner_id', $tenantCustomer->id)->where('tenant_id', $tenant->id)->where('currency', 'NGN')->where('status', 'active')->first();
            }
        } else {
            $wallet = DB::table('wallets')->where('owner_type', 'user')->where('owner_id', $request->user()->id)->whereNull('tenant_id')->where('currency', 'NGN')->where('status', 'active')->first();
        }
        abort_unless($wallet, 409, 'Your wallet is unavailable.');
        $reference = 'DEP-'.Str::upper(Str::random(20));
        DB::table('deposits')->insert(['wallet_id' => $wallet->id, 'reference' => $reference, 'gateway' => $data['gateway'],
            'amount_minor' => $amountMinor, 'currency' => 'NGN', 'status' => 'initiated', 'created_at' => now(), 'updated_at' => now()]);
        try {
            $result = $gateways->for($data['gateway'])->initiate($reference, $amountMinor, 'NGN', [
                'id' => $request->user()->id, 'name' => $request->user()->name, 'email' => $request->user()->email,
            ]);
            DB::table('deposits')->where('reference', $reference)->update(['gateway_reference' => $result['gateway_transaction_reference'] ?? ($result['gateway_reference'] ?? $reference),
                'status' => 'pending', 'verification_metadata' => json_encode(['authorization_url' => $result['authorization_url'] ?? null,
                    'gateway_transaction_reference' => $result['gateway_transaction_reference'] ?? null]), 'updated_at' => now()]);
            if (empty($result['authorization_url'])) return back()->with('status', 'Payment created. Check your deposit history for the payment status.');
            $this->assertTrustedCheckoutUrl($data['gateway'], (string) $result['authorization_url']);
            return redirect()->away($result['authorization_url']);
        } catch (Throwable $exception) {
            DB::table('deposits')->where('reference', $reference)->update(['status' => 'pending', 'updated_at' => now()]);
            report($exception);
            return back()->withErrors(['deposit' => 'The payment request could not be confirmed. It has been kept pending for reconciliation. Please do not pay again until you check its status.']);
        }
    }

    private function assertTrustedCheckoutUrl(string $gateway, string $url): void
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowedRoot = match ($gateway) { 'paystack' => ['paystack.com', 'paystack.co'], 'opay' => ['opaycheckout.com'], 'monnify' => ['monnify.com'], default => [] };
        $allowed = str_starts_with($url, 'https://') && collect($allowedRoot)->contains(fn ($root) => $host === $root || str_ends_with($host, '.'.$root));
        abort_unless($allowed, 502, 'Payment gateway returned an untrusted checkout URL.');
    }

    public function webhook(string $gateway, Request $request, PaymentGatewayRegistry $gateways, DepositPostingService $posting)
    {
        try { $adapter = $gateways->for($gateway); } catch (Throwable) { abort(404); }
        $allowedIps = config('payment_gateways.webhook_ip_allowlists.'.$gateway, []);
        if ($allowedIps !== []) abort_unless(in_array($request->ip(), $allowedIps, true), 403, 'Payment notification source IP is not allowlisted.');
        $raw = $request->getContent(); $headers = collect($request->headers->all())->map(fn ($value) => is_array($value) ? ($value[0] ?? '') : $value)->all();
        $signatureValid = $adapter->verifyWebhookSignature($raw, $headers);
        $sandboxMonnify = $gateway === 'monnify' && config('payment_gateways.monnify.environment') !== 'live';
        $signatureHeaderPresent = isset($headers['monnify-signature']) || isset($headers['Monnify-Signature']);
        if (! $signatureValid && (! $sandboxMonnify || $signatureHeaderPresent)) abort(401, 'Invalid payment notification signature.');
        $payload = json_decode($raw, true);
        if (! is_array($payload)) abort(400, 'Invalid payment notification payload.');
        if ($gateway === 'paystack' && ($payload['event'] ?? '') !== 'charge.success') {
            $eventType = strtolower((string) ($payload['event'] ?? ''));
            if (str_contains($eventType, 'dispute') || str_contains($eventType, 'refund') || str_contains($eventType, 'reversal')) {
                $ref = (string) (data_get($payload, 'data.reference') ?? data_get($payload, 'data.transaction.reference') ?? '');
                if ($ref !== '') $posting->flagUnderReview($gateway, $ref, 'Paystack sent a refund, dispute or reversal notification.', ['event_type' => $eventType]);
            }
            return response()->json(['received' => true]);
        }
        if ($gateway === 'opay' && (bool) data_get($payload, 'payload.refunded', false)) {
            $posting->flagUnderReview($gateway, (string) data_get($payload, 'payload.reference', ''), 'OPay reported that this transaction was refunded.', ['event_type' => 'refund']);
            return response()->json(['received' => true]);
        }
        if ($gateway === 'monnify' && ($payload['eventType'] ?? '') !== 'SUCCESSFUL_TRANSACTION') {
            $eventType = strtoupper((string) ($payload['eventType'] ?? ''));
            if (str_contains($eventType, 'REFUND') || str_contains($eventType, 'REVERS') || str_contains($eventType, 'REJECT')) {
                $ref = (string) (data_get($payload, 'eventData.paymentReference') ?? DB::table('deposits')->where('gateway','monnify')->where('gateway_reference',(string) data_get($payload,'eventData.transactionReference',''))->value('reference') ?? '');
                if ($ref !== '') $posting->flagUnderReview($gateway, $ref, 'Monnify sent a refund, reversal or rejected-payment notification.', ['event_type' => $eventType]);
            }
            return response()->json(['received' => true]);
        }
        $reference = match ($gateway) {
            'paystack' => (string) data_get($payload, 'data.reference', ''),
            'opay' => (string) data_get($payload, 'payload.reference', ''),
            'monnify' => (string) (data_get($payload, 'eventData.paymentReference') ?? data_get($payload, 'eventData.paymentReference', '')),
            default => '',
        };
        if ($reference === '') abort(400, 'Payment reference is missing.');
        $deposit = DB::table('deposits')->where('reference', $reference)->where('gateway', $gateway)->first();
        if (! $deposit) return response()->json(['received' => true]);
        $verified = $adapter->verify($reference);
        $eventId = (string) (data_get($payload, 'data.id') ?? data_get($payload, 'payload.transactionId') ?? data_get($payload, 'eventData.transactionReference') ?? hash('sha256', $raw));
        if ((($verified['status'] ?? '') === 'successful' && (int) ($verified['amount_minor'] ?? -1) !== (int) $deposit->amount_minor)
            || (in_array(($verified['status'] ?? ''), ['successful', 'failed'], true)
                && (strtoupper((string) ($verified['currency'] ?? '')) !== $deposit->currency
                    || (string) ($verified['reference'] ?? '') !== $reference))) {
            $posting->flagUnderReview($gateway, $reference, 'Verified provider amount, currency or reference did not match the deposit.', [
                'expected_amount_minor' => (int) $deposit->amount_minor, 'received_amount_minor' => (int) ($verified['amount_minor'] ?? -1),
                'expected_currency' => $deposit->currency, 'received_currency' => $verified['currency'] ?? null]);
        } elseif (($verified['status'] ?? 'pending') === 'under_review') {
            $posting->flagUnderReview($gateway, $reference, 'Gateway verification returned a partial, overpaid, reversed or voided status.');
        } elseif (($verified['status'] ?? 'pending') === 'successful') {
            $posting->recordVerifiedSuccess($gateway, $eventId, $reference, $verified, $signatureValid);
        } elseif (($verified['status'] ?? '') === 'failed') {
            $posting->recordVerifiedFailure($gateway, $eventId, $reference, $verified, $signatureValid);
        }
        return response()->json(['received' => true]);
    }

    public function returnFromGateway(string $gateway, Request $request, PaymentGatewayRegistry $gateways, DepositPostingService $posting): RedirectResponse
    {
        $reference = (string) ($request->query('reference') ?? $request->query('paymentReference') ?? '');
        if ($reference !== '') {
            $deposit = DB::table('deposits')->where('reference', $reference)->where('gateway', $gateway)->first();
            if ($deposit) {
                try {
                    $verified = $gateways->for($gateway)->verify($reference);
                    $eventId = 'return-'.hash('sha256', $gateway.'|'.$reference.'|'.json_encode($verified));
                    if ((($verified['status'] ?? '') === 'successful' && (int) ($verified['amount_minor'] ?? -1) !== (int) $deposit->amount_minor)
                        || (in_array(($verified['status'] ?? ''), ['successful', 'failed'], true)
                            && (strtoupper((string) ($verified['currency'] ?? '')) !== $deposit->currency
                                || (string) ($verified['reference'] ?? '') !== $reference))) {
                        $posting->flagUnderReview($gateway, $reference, 'Return verification amount, currency or reference mismatch.');
                    } elseif (($verified['status'] ?? '') === 'under_review') $posting->flagUnderReview($gateway, $reference, 'Gateway return verification requires review.');
                    elseif (($verified['status'] ?? '') === 'successful') $posting->recordVerifiedSuccess($gateway, $eventId, $reference, $verified, false);
                    elseif (($verified['status'] ?? '') === 'failed') $posting->recordVerifiedFailure($gateway, $eventId, $reference, $verified, false);
                } catch (Throwable $exception) { report($exception); }
            }
        }
        $tenant = $request->attributes->get('tenant');
        if ($tenant && $request->user() && $request->user()->tenants()->whereKey($tenant->id)->wherePivot('status', 'active')->exists()) {
            return redirect()->route('tenant.dashboard')->with('status', 'Payment status is being verified directly with the payment provider. Check your wallet and deposit history before trying again.');
        }
        if ($tenant && $request->user() && DB::table('tenant_customers')->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->where('status', 'active')->exists()) {
            return redirect()->route('tenant.customer.dashboard')->with('status', 'Payment status is being verified directly with the payment provider. Check your wallet and deposit history before trying again.');
        }
        return redirect()->route('dashboard')->with('status', 'Payment status is being verified directly with the payment provider. Check your wallet and deposit history before trying again.');
    }
    public function reverify(int $deposit, Request $request, PaymentGatewayRegistry $gateways, DepositPostingService $posting, \App\Services\AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['super-admin', 'finance-admin']), 403);
        $record = DB::table('deposits')->where('id', $deposit)->first();
        abort_unless($record, 404);
        abort_if($record->ledger_journal_id !== null, 409, 'This deposit already has a credit journal. Reversal review is required; do not credit it again.');
        abort_unless(in_array($record->status, ['pending', 'under_review', 'initiated'], true), 409, 'Only pending or under-review deposits can be reverified.');
        try { $verified = $gateways->for($record->gateway)->verify($record->reference); }
        catch (Throwable $exception) { report($exception); return back()->withErrors(['deposit' => 'The gateway verification request failed. The deposit remains unchanged.']); }
        $eventId = 'admin-reverify-'.hash('sha256', $record->gateway.'|'.$record->reference.'|'.json_encode($verified));
        if (($verified['status'] ?? '') === 'under_review') {
            $posting->flagUnderReview($record->gateway, $record->reference, 'Manual gateway verification returned an overpaid, partially paid, reversed or voided status.');
            $audit->record('deposit.reverification_review', null, 'deposits', (string) $deposit, 'Gateway status requires manual review', ['status' => $verified['status']]);
            return back()->withErrors(['deposit' => 'The provider returned a review status. No additional credit was posted.']);
        }
        if (($verified['status'] ?? '') === 'successful'
            && (int) ($verified['amount_minor'] ?? -1) === (int) $record->amount_minor
            && strtoupper((string) ($verified['currency'] ?? '')) === $record->currency
            && (string) ($verified['reference'] ?? '') === $record->reference) {
            $posted = $posting->recordVerifiedSuccess($record->gateway, $eventId, $record->reference, $verified, false);
            $audit->record('deposit.reverified_success', null, 'deposits', (string) $deposit, 'Gateway status reverified server-to-server', ['posted' => $posted]);
            return back()->with('status', $posted ? 'Deposit verified and credited once.' : 'Deposit was already processed or requires further review.');
        }
        if (($verified['status'] ?? '') === 'failed'
            && strtoupper((string) ($verified['currency'] ?? '')) === $record->currency
            && (string) ($verified['reference'] ?? '') === $record->reference) {
            $posting->recordVerifiedFailure($record->gateway, $eventId, $record->reference, $verified, false);
            $audit->record('deposit.reverified_failure', null, 'deposits', (string) $deposit, 'Gateway confirmed payment failure', []);
            return back()->with('status', 'The payment provider confirmed failure. The deposit was not credited.');
        }
        $posting->flagUnderReview($record->gateway, $record->reference, 'Manual re-verification did not produce an exact, credit-eligible payment match.');
        $audit->record('deposit.reverification_exception', null, 'deposits', (string) $deposit, 'Gateway verification requires manual investigation', ['status' => $verified['status'] ?? 'unknown']);
        return back()->withErrors(['deposit' => 'The provider response did not exactly match this deposit. It remains under review and has not been credited.']);
    }

}
