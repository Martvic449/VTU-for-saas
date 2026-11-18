<?php

namespace App\Services\Providers;

use App\Contracts\Providers\VtuProvider;
use App\Models\Provider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use InvalidArgumentException;
use App\Services\Webhooks\WebhookUrlPolicy;

/** Generic JSON transport; it is not a claim of compatibility with any named supplier. */
class ConfiguredHttpVtuProvider implements VtuProvider
{
    public function __construct(private readonly Provider $provider) {}

    public function purchase(string $serviceCode, array $payload, string $idempotencyReference): array
    {
        $settings = $this->settings();
        $url = $settings['purchase_url'] ?? null;
        if (! is_string($url)) throw new RuntimeException('Provider purchase URL is missing or is not HTTPS.');
        $urlOptions = $this->urlOptions($url);
        $context = ['service_code' => $serviceCode, 'product_code' => $payload['product_code'] ?? $serviceCode,
            'customer' => array_diff_key($payload, ['product_code' => true, '_provider_mapping' => true]), 'reference' => $idempotencyReference];
        $body = $this->mappedBody((array) ($settings['purchase_fields'] ?? []), $context)
            ?: ['service_code' => $serviceCode, 'product_code' => $context['product_code'], 'customer' => $context['customer'], 'reference' => $idempotencyReference];
        try {
            $request = Http::withOptions($urlOptions)->withHeaders($this->headers($idempotencyReference))->acceptJson()->asJson()
                ->connectTimeout((int) ($settings['connect_timeout_seconds'] ?? 5))->timeout((int) ($settings['timeout_seconds'] ?? 20));
            $response = strtoupper((string) ($settings['purchase_method'] ?? 'POST')) === 'GET'
                ? $request->get($url, $body) : $request->post($url, $body);
        } catch (ConnectionException) {
            return ['outcome' => 'pending', 'message' => 'Provider connection outcome is unknown.'];
        }
        return $this->normalize($response->json() ?? [], $response->status());
    }

    public function requery(string $providerReference, string $idempotencyReference): array
    {
        $settings = $this->settings();
        $url = $settings['requery_url'] ?? null;
        if (! is_string($url)) throw new RuntimeException('Provider requery URL is missing or is not HTTPS.');
        $urlOptions = $this->urlOptions($url);
        $context = ['provider_reference' => $providerReference, 'reference' => $idempotencyReference];
        $body = $this->mappedBody((array) ($settings['requery_fields'] ?? []), $context)
            ?: [(string) ($settings['requery_reference_parameter'] ?? 'reference') => $providerReference, 'client_reference' => $idempotencyReference];
        try {
            $request = Http::withOptions($urlOptions)->withHeaders($this->headers($idempotencyReference))->acceptJson()->asJson()
                ->connectTimeout((int) ($settings['connect_timeout_seconds'] ?? 5))->timeout((int) ($settings['timeout_seconds'] ?? 20));
            $response = strtoupper((string) ($settings['requery_method'] ?? 'GET')) === 'POST'
                ? $request->post($url, $body) : $request->get($url, $body);
        } catch (ConnectionException) {
            return ['outcome' => 'pending', 'message' => 'Provider requery outcome is unknown.'];
        }
        return $this->normalize($response->json() ?? [], $response->status());
    }

    private function mappedBody(array $fields, array $context): array
    {
        $body = [];
        foreach ($fields as $target => $source) {
            if (! is_string($target) || $target === '') continue;
            if (is_string($source)) $body[$target] = data_get($context, $source, $source);
            else $body[$target] = $source;
        }
        return $body;
    }

    private function normalize(array $body, int $httpStatus): array
    {
        $settings = $this->settings();
        $raw = strtolower((string) data_get($body, $settings['response_status_path'] ?? 'status', ''));
        $success = array_map('strtolower', $settings['success_statuses'] ?? ['success', 'successful', 'completed']);
        $failed = array_map('strtolower', $settings['failed_statuses'] ?? ['failed', 'failure', 'rejected']);
        $httpIsSuccessful = $httpStatus >= 200 && $httpStatus < 300;
        $outcome = ! $httpIsSuccessful ? 'pending' : (in_array($raw, $success, true) ? 'successful' : (in_array($raw, $failed, true) ? 'failed' : 'pending'));
        $cost = data_get($body, $settings['response_cost_path'] ?? 'cost_minor');
        $actualCostMinor = ($settings['response_cost_unit'] ?? 'kobo') === 'naira' ? $this->nairaToMinor($cost) : (is_numeric($cost) ? (int) $cost : null);
        return ['outcome' => $outcome,
            'provider_reference' => (string) data_get($body, $settings['response_reference_path'] ?? 'reference', ''),
            'message' => substr((string) data_get($body, $settings['response_message_path'] ?? 'message', 'HTTP '.$httpStatus), 0, 500),
            'data' => ['http_status' => $httpStatus, 'provider_status' => $raw,
                'actual_cost_minor' => $actualCostMinor]];
    }

    private function nairaToMinor(mixed $amount): ?int
    {
        if (! is_numeric($amount)) return null;
        $parts = explode('.', (string) $amount, 2);
        if (! ctype_digit(ltrim($parts[0], '+')) || (isset($parts[1]) && ! ctype_digit($parts[1]))) return null;
        return ((int) $parts[0] * 100) + (int) str_pad(substr($parts[1] ?? '0', 0, 2), 2, '0');
    }

    private function settings(): array { return is_array($this->provider->settings) ? $this->provider->settings : []; }

    private function headers(?string $idempotencyReference = null): array
    {
        $credentials = is_array($this->provider->credentials_encrypted) ? $this->provider->credentials_encrypted : [];
        $settings = $this->settings();
        $token = (string) ($credentials['api_token'] ?? '');
        if ($token === '') throw new RuntimeException('Provider credentials are not configured.');
        $headers = [(string) ($settings['auth_header'] ?? 'Authorization') => (string) ($settings['auth_prefix'] ?? 'Bearer ').$token];
        if ($idempotencyReference !== null) $headers[(string) ($settings['idempotency_header'] ?? 'Idempotency-Key')] = $idempotencyReference;
        return $headers;
    }

    private function urlOptions(string $url): array
    {
        try {
            return app(WebhookUrlPolicy::class)->assertPublicHttps($url);
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException('Provider URL must resolve to a public HTTPS destination.', 0, $exception);
        }
    }
}
