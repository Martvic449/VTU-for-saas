<?php

namespace App\Services\Payments;

use App\Services\Accounting\LedgerService;
use App\Services\Webhooks\WebhookEventDispatcher;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Deposit callback handler. The gateway adapter must verify its signature and server-side payment status first. */
final class DepositPostingService
{
    public function __construct(private readonly LedgerService $ledger, private readonly WebhookEventDispatcher $webhooks) {}

    public function recordVerifiedSuccess(string $gateway, string $eventId, string $depositReference, array $verifiedPayment, bool $signatureValid = true): bool
    {
        return DB::transaction(function () use ($gateway, $eventId, $depositReference, $verifiedPayment, $signatureValid): bool {
            $inserted = DB::table('payment_events')->insertOrIgnore([
                'gateway' => $gateway,
                'event_id' => $eventId,
                'event_type' => 'payment.success',
                'deposit_reference' => $depositReference,
                'signature_valid' => $signatureValid,
                'payload_redacted' => json_encode(['reference' => $depositReference, 'status' => 'verified']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($inserted === 0) {
                return false;
            }

            $deposit = DB::table('deposits')->where('reference', $depositReference)->lockForUpdate()->first();
            if (! $deposit || $deposit->gateway !== $gateway) {
                throw new RuntimeException('Deposit reference is invalid for this gateway.');
            }
            if ($deposit->status === 'successful' || $deposit->ledger_journal_id !== null) {
                DB::table('payment_events')->where('gateway', $gateway)->where('event_id', $eventId)->update(['processed_at' => now(), 'updated_at' => now()]);
                return false;
            }
            if (! in_array($deposit->status, ['initiated', 'pending', 'under_review'], true)) {
                throw new RuntimeException('Deposit is not in a credit-eligible state.');
            }
            if (($verifiedPayment['status'] ?? null) !== 'successful'
                || (int) ($verifiedPayment['amount_minor'] ?? -1) !== (int) $deposit->amount_minor
                || strtoupper((string) ($verifiedPayment['currency'] ?? '')) !== $deposit->currency
                || (string) ($verifiedPayment['reference'] ?? '') !== $depositReference) {
                throw new RuntimeException('Gateway verification does not match the expected deposit.');
            }

            $walletAccount = DB::table('wallet_ledger_accounts')->where('wallet_id', $deposit->wallet_id)->first();
            $clearingAccount = DB::table('ledger_accounts')->where('code', 'PAYMENT_CLEARING_NGN')->first();
            if (! $walletAccount || ! $clearingAccount) {
                throw new RuntimeException('Wallet ledger mapping or payment clearing account is not configured.');
            }

            $journalId = $this->ledger->post('wallet.deposit', $deposit->currency, [
                ['account_id' => (int) $clearingAccount->id, 'side' => 'debit', 'amount_minor' => (int) $deposit->amount_minor],
                ['account_id' => (int) $walletAccount->ledger_account_id, 'side' => 'credit', 'amount_minor' => (int) $deposit->amount_minor],
            ], 'DEP-'.$deposit->reference, null, 'Verified wallet deposit');

            DB::table('deposits')->where('id', $deposit->id)->update([
                'status' => 'successful', 'ledger_journal_id' => $journalId, 'verified_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('payment_events')->where('gateway', $gateway)->where('event_id', $eventId)->update(['processed_at' => now(), 'updated_at' => now()]);
            $wallet = DB::table('wallets')->where('id', $deposit->wallet_id)->first();
            $this->webhooks->dispatch('wallet.deposit.successful', ['deposit_reference' => $deposit->reference, 'amount_minor' => (int) $deposit->amount_minor, 'currency' => $deposit->currency], $wallet?->tenant_id ? (int) $wallet->tenant_id : null);
            return true;
        }, 3);
    }
    public function flagUnderReview(string $gateway, string $depositReference, string $reason, array $signals = []): void
    {
        DB::transaction(function () use ($gateway, $depositReference, $reason, $signals): void {
            $deposit = DB::table('deposits')->where('reference', $depositReference)->where('gateway', $gateway)->lockForUpdate()->first();
            if (! $deposit || $deposit->status === 'under_review') return;
            DB::table('deposits')->where('id', $deposit->id)->update(['status' => 'under_review', 'verification_metadata' => json_encode(['review_reason' => $reason, 'signals' => $signals]), 'updated_at' => now()]);
            $wallet = DB::table('wallets')->where('id', $deposit->wallet_id)->first();
            DB::table('risk_events')->insert(['tenant_id' => $wallet?->tenant_id, 'user_id' => $wallet?->user_id,
                'subject_type' => 'deposit', 'subject_id' => (string) $deposit->id, 'rule_code' => 'payment.verification_mismatch',
                'severity' => 'high', 'signals_redacted' => json_encode(['gateway' => $gateway, 'reference' => $depositReference, 'reason' => $reason] + $signals),
                'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        }, 3);
    }

    public function recordVerifiedFailure(string $gateway, string $eventId, string $depositReference, array $verifiedPayment, bool $signatureValid = true): bool
    {
        return DB::transaction(function () use ($gateway, $eventId, $depositReference, $verifiedPayment, $signatureValid): bool {
            $deposit = DB::table('deposits')->where('reference', $depositReference)->where('gateway', $gateway)->lockForUpdate()->first();
            if (! $deposit || $deposit->ledger_journal_id !== null || ! in_array($deposit->status, ['initiated', 'pending', 'under_review'], true)) return false;
            if (($verifiedPayment['status'] ?? null) !== 'failed'
                || strtoupper((string) ($verifiedPayment['currency'] ?? '')) !== $deposit->currency
                || (string) ($verifiedPayment['reference'] ?? '') !== $depositReference) return false;
            $inserted = DB::table('payment_events')->insertOrIgnore([
                'gateway' => $gateway, 'event_id' => $eventId, 'event_type' => 'payment.failed',
                'deposit_reference' => $depositReference, 'signature_valid' => $signatureValid,
                'payload_redacted' => json_encode(['reference' => $depositReference, 'status' => 'verified_failed']),
                'processed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($inserted === 0) return false;
            DB::table('deposits')->where('id', $deposit->id)->whereIn('status', ['initiated', 'pending', 'under_review'])->update(['status' => 'failed', 'updated_at' => now()]);
            return true;
        }, 3);
    }

}
