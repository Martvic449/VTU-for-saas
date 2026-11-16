<?php

namespace App\Services\Providers;

use App\Contracts\Providers\VtuProvider;
use App\Models\Provider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** VTpass adapter. Service/product field mappings are configured per provider-service record. */
final class VtpassVtuProvider implements VtuProvider
{
    public function __construct(private readonly Provider $provider) {}

    public function purchase(string $serviceCode, array $payload, string $idempotencyReference): array
    {
        if (! preg_match('/^\d{12}[A-Za-z0-9]+$/', $idempotencyReference)) throw new RuntimeException('VTpass request_id must begin with the 12-digit Africa/Lagos YYYYMMDDHHMM timestamp and include a unique suffix.');
        $credentials = $this->credentials();
        $mapping = (array) ($payload['_provider_mapping'] ?? []);
        unset($payload['_provider_mapping']);
        $serviceId = (string) ($mapping['service_id'] ?? data_get($this->provider->settings, 'service_ids.'.$serviceCode, ''));
        if ($serviceId === '') throw new RuntimeException('VTpass serviceID is not mapped for '.$serviceCode.'.');

        $body = ['request_id' => $idempotencyReference, 'serviceID' => $serviceId];
        $fields = (array) ($mapping['request_fields'] ?? []);
        foreach ($fields as $target => $source) {
            if (is_string($source) && array_key_exists($source, $payload)) $body[$target] = $payload[$source];
            elseif (! is_string($source)) $body[$target] = $source;
        }
        if (isset($payload['amount']) && ! isset($body['amount'])) $body['amount'] = $payload['amount'];
        if (isset($payload['phone']) && ! isset($body['phone'])) $body['phone'] = $payload['phone'];
        if (isset($payload['variation_code']) && ! isset($body['variation_code'])) $body['variation_code'] = $payload['variation_code'];
        if (isset($payload['billersCode']) && ! isset($body['billersCode'])) $body['billersCode'] = $payload['billersCode'];
        if (isset($payload['quantity']) && ! isset($body['quantity'])) $body['quantity'] = $payload['quantity'];

        return $this->send('pay', $body, $credentials, true);
    }

    public function requery(string $providerReference, string $idempotencyReference): array
    {
        return $this->send('requery', ['request_id' => $idempotencyReference], $this->credentials(), true);
    }

    private function send(string $path, array $body, array $credentials, bool $post): array
    {
        $base = rtrim((string) data_get($this->provider->settings, 'base_url', $this->provider->environment === 'live' ? 'https://vtpass.com/api' : 'https://sandbox.vtpass.com/api'), '/');
        if (! str_starts_with($base, 'https://') || ! in_array(parse_url($base, PHP_URL_HOST), ['vtpass.com', 'sandbox.vtpass.com'], true)) {
            throw new RuntimeException('VTpass base URL must be the official HTTPS VTpass host.');
        }
        $headers = ['api-key' => (string) ($credentials['api_key'] ?? ''), 'Accept' => 'application/json'];
        if ($post) $headers['secret-key'] = (string) ($credentials['secret_key'] ?? '');
        else $headers['public-key'] = (string) ($credentials['public_key'] ?? '');
        if ($headers['api-key'] === '' || ($post && $headers['secret-key'] === '')) throw new RuntimeException('VTpass API credentials are incomplete.');
        try {
            $request = Http::withHeaders($headers)->acceptJson()->asJson()->connectTimeout(5)->timeout(25);
            $response = $post ? $request->post($base.'/'.$path, $body) : $request->get($base.'/'.$path, $body);
        } catch (ConnectionException) {
            return ['outcome' => 'pending', 'message' => 'VTpass connection outcome is unknown.'];
        }
        $data = $response->json() ?? [];
        if (! $response->successful() || ! is_array($data)) return ['outcome' => 'pending', 'message' => 'VTpass returned a non-success HTTP response.'];
        $status = strtolower((string) (data_get($data, 'content.transactions.status') ?? data_get($data, 'content.transactions.transaction_status') ?? data_get($data, 'status') ?? ''));
        $code = (string) ($data['code'] ?? '');
        $outcome = in_array($status, ['delivered', 'successful', 'success', 'completed'], true)
            ? 'successful'
            : (in_array($status, ['failed', 'failure', 'reversed', 'declined'], true) || in_array($code, ['016', '091'], true) ? 'failed' : 'pending');
        $actualNaira = data_get($data, 'content.transactions.total_amount');
        return ['outcome' => $outcome, 'provider_reference' => (string) (data_get($data, 'content.transactions.transactionId') ?? $data['requestId'] ?? $data['request_id'] ?? $body['request_id'] ?? ''),
            'message' => substr((string) ($data['response_description'] ?? $data['message'] ?? 'VTpass response'), 0, 500),
            'data' => ['provider_status' => $status ?: $code, 'actual_cost_minor' => $this->nairaToMinor($actualNaira),
                'purchased_code' => $data['purchased_code'] ?? null]];
    }

    private function nairaToMinor(mixed $amount): ?int
    {
        if (! is_numeric($amount)) return null;
        $parts = explode('.', (string) $amount, 2);
        if (! ctype_digit(ltrim($parts[0], '+')) || (isset($parts[1]) && ! ctype_digit($parts[1]))) return null;
        return ((int) $parts[0] * 100) + (int) str_pad(substr($parts[1] ?? '0', 0, 2), 2, '0');
    }

    private function credentials(): array
    {
        $value = $this->provider->credentials_encrypted;
        return is_array($value) ? $value : [];
    }
}
