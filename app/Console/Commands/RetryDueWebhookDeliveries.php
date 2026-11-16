<?php

namespace App\Console\Commands;

use App\Jobs\DeliverWebhook;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class RetryDueWebhookDeliveries extends Command
{
    protected $signature = 'webhooks:retry-due {--limit=100}';
    protected $description = 'Re-dispatch due or stale webhook deliveries after queue/worker interruptions';

    public function handle(): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $ids = DB::table('webhook_deliveries')->where(function ($query): void {
            $query->where(function ($due): void {
                $due->whereIn('status', ['pending', 'retrying'])->where(function ($time): void {
                    $time->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
                });
            })->orWhere(function ($stale): void {
                $stale->whereIn('status', ['queued', 'processing'])->where('updated_at', '<=', now()->subMinutes(5));
            });
        })->orderBy('updated_at')->limit($limit)->pluck('id');

        $queued = 0;
        foreach ($ids as $id) {
            $shouldDispatch = DB::transaction(function () use ($id): bool {
                $delivery = DB::table('webhook_deliveries')->where('id', $id)->lockForUpdate()->first();
                if (! $delivery || in_array($delivery->status, ['delivered', 'abandoned'], true)) return false;
                $due = in_array($delivery->status, ['pending', 'retrying'], true)
                    && ($delivery->next_attempt_at === null || $delivery->next_attempt_at <= now()->toDateTimeString());
                $stale = in_array($delivery->status, ['queued', 'processing'], true)
                    && $delivery->updated_at <= now()->subMinutes(5)->toDateTimeString();
                if (! $due && ! $stale) return false;
                DB::table('webhook_deliveries')->where('id', $id)->update(['status' => 'queued', 'updated_at' => now()]);
                return true;
            }, 3);
            if ($shouldDispatch) {
                DeliverWebhook::dispatch((int) $id)->afterCommit();
                $queued++;
            }
        }
        $this->info('Re-dispatched '.$queued.' due/stale webhook delivery job(s).');
        return self::SUCCESS;
    }
}
