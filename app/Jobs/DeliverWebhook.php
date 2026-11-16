<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookSigner;
use App\Services\Webhooks\WebhookUrlPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

final class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 1;
    public int $timeout = 15;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(WebhookSigner $signer, WebhookUrlPolicy $urlPolicy): void
    {
        // Atomic claim prevents duplicate queue dispatches from producing duplicate concurrent deliveries.
        $delivery = DB::transaction(function (): ?WebhookDelivery {
            $record = WebhookDelivery::query()->lockForUpdate()->find($this->deliveryId);
            if (! $record || in_array($record->status, ['delivered', 'abandoned'], true)) return null;
            if ($record->status === 'processing' && $record->updated_at && $record->updated_at->gt(now()->subMinutes(5))) return null;
            if ($record->status === 'retrying' && $record->next_attempt_at && $record->next_attempt_at->isFuture()) return null;
            $record->update(['status' => 'processing', 'attempts' => (int) $record->attempts + 1, 'updated_at' => now()]);
            return $record->fresh();
        }, 3);
        if (! $delivery) return;

        $endpoint = WebhookEndpoint::query()->find($delivery->webhook_endpoint_id);
        if (! $endpoint || $endpoint->status !== 'active') {
            $delivery->update(['status' => 'abandoned', 'updated_at' => now()]);
            return;
        }
        $secret = (string) $endpoint->secret_encrypted;
        try {
            $urlPolicy->assertPublicHttps($endpoint->url);
        } catch (Throwable $exception) {
            $delivery->update(['status' => 'abandoned', 'updated_at' => now()]);
            report($exception);
            return;
        }

        $body = json_encode(['id' => $delivery->event_id, 'type' => $delivery->event_type, 'created_at' => now()->toISOString(), 'data' => $delivery->payload], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->timestamp;
        $signature = $signer->sign($secret, (int) $timestamp, $body);
        try {
            $response = Http::withoutRedirecting()->connectTimeout(4)->timeout(10)->withHeaders([
                'Content-Type' => 'application/json', 'X-VTU-Event' => $delivery->event_type,
                'X-VTU-Delivery' => $delivery->event_id, 'X-VTU-Timestamp' => $timestamp, 'X-VTU-Signature' => $signature,
            ])->withBody($body, 'application/json')->post($endpoint->url);
            if ($response->successful()) {
                $delivery->update(['last_http_status' => $response->status(), 'status' => 'delivered', 'delivered_at' => now(), 'next_attempt_at' => null, 'updated_at' => now()]);
                return;
            }
            $delivery->update(['last_http_status' => $response->status(), 'updated_at' => now()]);
            $this->retryOrAbandon($delivery->fresh());
        } catch (Throwable $exception) {
            report($exception);
            $this->retryOrAbandon($delivery->fresh());
        }
    }

    private function retryOrAbandon(WebhookDelivery $delivery): void
    {
        if ((int) $delivery->attempts >= 8) {
            $delivery->update(['status' => 'abandoned', 'next_attempt_at' => null, 'updated_at' => now()]);
            return;
        }
        $delay = $this->backoff((int) $delivery->attempts);
        $delivery->update(['status' => 'retrying', 'next_attempt_at' => now()->addSeconds($delay), 'updated_at' => now()]);
        self::dispatch($delivery->id)->delay(now()->addSeconds($delay));
    }

    private function backoff(int $attempt): int { return min(3600, 30 * (2 ** max(0, $attempt - 1))); }
}
