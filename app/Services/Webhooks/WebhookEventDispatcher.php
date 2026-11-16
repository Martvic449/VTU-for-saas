<?php
namespace App\Services\Webhooks;
use App\Jobs\DeliverWebhook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
final class WebhookEventDispatcher
{
    public function dispatch(string $eventType, array $payload, ?int $tenantId): void
    {
        $query = DB::table('webhook_endpoints')->where('status', 'active')->whereJsonContains('events', $eventType);
        $tenantId === null ? $query->whereNull('tenant_id') : $query->where('tenant_id', $tenantId);
        foreach ($query->get() as $endpoint) {
            $deliveryId = DB::table('webhook_deliveries')->insertGetId([
                'webhook_endpoint_id' => $endpoint->id, 'event_id' => (string) Str::uuid(), 'event_type' => $eventType,
                'payload' => json_encode($payload), 'status' => 'pending', 'attempts' => 0, 'next_attempt_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::afterCommit(fn () => DeliverWebhook::dispatch($deliveryId));
        }
    }
}
