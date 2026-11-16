<?php

namespace App\Services\Transactions;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class TransactionStateMachine
{
    private const TRANSITIONS = [
        'created' => ['validated', 'failed'],
        'validated' => ['reserved', 'failed'],
        'reserved' => ['submitted', 'failed'],
        'submitted' => ['pending', 'successful', 'failed'],
        'pending' => ['successful', 'failed'],
        'successful' => [],
        'failed' => [],
        'reversed' => [],
        'refunded' => [],
    ];

    public function transition(int $transactionId, string $next, array $attributes = []): void
    {
        DB::transaction(function () use ($transactionId, $next, $attributes): void {
            $transaction = DB::table('transactions')->where('id', $transactionId)->lockForUpdate()->first();
            if (! $transaction) {
                throw new RuntimeException('Transaction not found.');
            }
            $allowed = self::TRANSITIONS[$transaction->status] ?? [];
            if (! in_array($next, $allowed, true)) {
                throw new RuntimeException("Invalid transaction transition {$transaction->status} -> {$next}.");
            }
            $safe = array_intersect_key($attributes, array_flip(['provider_reference', 'failure_reason', 'submitted_at', 'completed_at']));
            DB::table('transactions')->where('id', $transactionId)->update($safe + ['status' => $next, 'updated_at' => now()]);
        }, 3);
    }

    public function canRetryProviderRequest(string $status): bool
    {
        // A submitted/pending outcome is ambiguous. It must be requeried, not blindly retried.
        return in_array($status, ['created', 'validated', 'reserved'], true);
    }
}
