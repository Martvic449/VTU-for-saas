<?php
namespace App\Console\Commands;
use App\Jobs\RequeryVtuTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
final class RequeryPendingTransactions extends Command
{
    protected $signature = 'vtu:requery-pending {--limit=100}';
    protected $description = 'Queue safe status requery for pending VTU transactions with supplier references';
    public function handle(): int
    {
        $ids = DB::table('transactions')->join('transaction_attempts', function ($join): void {
            $join->on('transaction_attempts.transaction_id', '=', 'transactions.id')
                ->whereRaw('transaction_attempts.attempt_number = (select max(ta2.attempt_number) from transaction_attempts ta2 where ta2.transaction_id = transactions.id)');
        })->whereIn('transactions.status', ['submitted', 'pending'])
            ->whereNotNull('transaction_attempts.provider_reference')
            ->where('transactions.updated_at', '<=', now()->subMinutes(2))
            ->orderBy('transactions.updated_at')->limit(max(1, min(500, (int) $this->option('limit'))))
            ->pluck('transactions.id');
        foreach ($ids as $id) RequeryVtuTransaction::dispatch((int) $id);
        $stale = DB::table('transactions')->where('status', 'submitted')->whereNull('provider_reference')->where('updated_at', '<=', now()->subMinutes(5))->limit(100)->get();
        foreach ($stale as $tx) {
            DB::transaction(function () use ($tx): void {
                $locked = DB::table('transactions')->where('id', $tx->id)->lockForUpdate()->first();
                if (! $locked || $locked->status !== 'submitted' || $locked->provider_reference !== null) return;
                DB::table('transactions')->where('id', $tx->id)->update(['status' => 'pending', 'updated_at' => now()]);
                DB::table('outbox_events')->insert(['event_uuid' => (string) \Illuminate\Support\Str::uuid(), 'event_type' => 'transaction.manual_review_required',
                    'aggregate_type' => 'transaction', 'aggregate_id' => (string) $tx->id,
                    'payload' => json_encode(['reference' => $tx->reference, 'reason' => 'Submission has no provider reference; outcome requires manual reconciliation']),
                    'status' => 'pending', 'attempts' => 0, 'available_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            });
        }
        $this->info('Queued '.$ids->count().' transaction requery job(s); flagged '.$stale->count().' stale submission(s) for manual review.');
        return self::SUCCESS;
    }
}
