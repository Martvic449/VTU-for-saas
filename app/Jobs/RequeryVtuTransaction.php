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
use Throwable;

final class RequeryVtuTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 1;
    public int $timeout = 35;

    public function __construct(public readonly int $transactionId) {}

    public function handle(ProviderAdapterRegistry $adapters, VtuTransactionService $transactions): void
    {
        $tx = DB::table('transactions')->where('id', $this->transactionId)->first();
        if (! $tx || ! in_array($tx->status, ['submitted', 'pending'], true)) return;
        $attempt = DB::table('transaction_attempts')->where('transaction_id', $tx->id)->orderByDesc('attempt_number')->first();
        if (! $attempt || ! $attempt->provider_reference) return;
        $provider = Provider::query()->find($attempt->provider_id);
        if (! $provider || $provider->environment !== config('providers.environment', 'sandbox')) return;
        try { $result = $adapters->for($provider)->requery((string) $attempt->provider_reference, (string) $tx->reference); }
        catch (Throwable $exception) { report($exception); return; }
        if ($result['outcome'] === 'successful') {
            $snapshot = json_decode($tx->price_snapshot, true) ?: [];
            try {
                $transactions->settleSuccessful((int) $tx->id, (int) ($result['data']['actual_cost_minor'] ?? $snapshot['cost_minor'] ?? $tx->cost_minor));
            } catch (Throwable $exception) { report($exception); }
        } elseif ($result['outcome'] === 'failed') {
            $transactions->markAttemptConfirmedFailure((int) $tx->id, ($result['message'] ?? null) ?: 'Provider confirmed failure on requery.');
            SubmitVtuTransaction::dispatch((int) $tx->id)->afterCommit();
        } else {
            $transactions->markPending((int) $tx->id, ($result['provider_reference'] ?? null) ?: $attempt->provider_reference);
        }
    }
}
