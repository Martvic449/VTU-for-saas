<?php

namespace App\Services\Providers;

use App\Contracts\Providers\VtuProvider;
use App\Models\Provider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** SMEPlug v1 adapter based on the supplied official Postman collection. */
final class SmeplugVtuProvider implements VtuProvider
{
    private const BASE_URL = 'https://smeplug.ng/api/v1';

    public function __construct(private readonly Provider $provider) {}

    public function purchase(string $serviceCode, array $payload, string $idempotencyReference): array
    {
        $mapping = (array) ($payload['_provider_mapping'] ?? []);
        unset($payload['_provider_mapping']);
        $endpoint = (string) ($mapping['smeplug_endpoint'] ?? $this->endpointFor($serviceCode));
        if (! in_array($endpoint, ['data/purchase', 'airtime/purchase', 'vtu'], true)) {
            throw new RuntimeException('SMEPlug endpoint must be data/purchase, airtime/purchase or vtu.');
        }

        $body = [
            'network_id' => $this->requiredInteger($mapping['network_id'] ?? $payload['network_id'] ?? null, 'network_id'),
            'phone' => (string) ($payload['phone'] ?? ''),
            'customer_reference' => $idempotencyReference,
        ];
        if ($body['phone'] === '') throw new RuntimeException('SMEPlug purchase requires a recipient phone number.');

        if ($endpoint === 'data/purchase') {
            $body['plan_id'] = $this->requiredInteger($mapping['plan_id'] ?? $payload['plan_id'] ?? $payload['product_code'] ?? null, 'plan_id');
        } else {
            $amount = $payload['amount'] ?? null;
            if (! is_numeric($amount) || (float) $amount <= 0 || floor((float) $amount) !== (float) $amount) {
                throw new RuntimeException('SMEPlug airtime purchase requires a positive whole-naira amount.');
            }
            $body['amount'] = (int) $amount;
        }

        return $this->send('POST', self::BASE_URL.'/'.$endpoint, $body, true);
    }

    public function requery(string $providerReference, string $idempotencyReference): array
    {
        if ($providerReference === '') return ['outcome' => 'pending', 'message' => 'SMEPlug transaction reference is not available yet.'];
        $url = self::BASE_URL.'/transactions/'.rawurlencode($providerReference);
        return $this->send('GET', $url, [], false);
    }

    private function send(string $method, string $url, array $body, bool $purchase): array
    {
        $credentials = $this->provider->credentials_encrypted;
        $token = is_array($credentials) ? (string) ($credentials['private_key'] ?? $credentials['api_token'] ?? '') : '';
        if ($token === '') throw new RuntimeException('SMEPlug private key is not configured.');

        try {
            $request = Http::withToken($token)->acceptJson()->asJson()->connectTimeout(5)->timeout(25);
            $response = $method === 'POST' ? $request->post($url, $body) : $request->get($url);
        } catch (ConnectionException) {
            return ['outcome' => 'pending', 'message' => 'SMEPlug connection outcome is unknown.'];
        }

        $data = $response->json();
        if (! is_array($data)) return ['outcome' => 'pending', 'message' => 'SMEPlug returned a non-JSON or unrecognized response.'];
        $settings = is_array($this->provider->settings) ? $this->provider->settings : [];
        $statusPath = $settings['response_status_path'] ?? null;
        $referencePath = $settings['response_reference_path'] ?? null;
        $messagePath = $settings['response_message_path'] ?? null;
        // The collection has no saved response examples. Status/reference paths and terminal values
        // must therefore be supplied from an actual SMEPlug sandbox response before activation.
        if (! is_string($statusPath) || $statusPath === '' || ! is_string($referencePath) || $referencePath === '') {
            return ['outcome' => 'pending', 'message' => 'SMEPlug response mapping is incomplete; transaction requires requery/manual review.',
                'data' => ['http_status' => $response->status()]];
        }
        $rawStatus = strtolower(trim((string) data_get($data, $statusPath, '')));
        $success = array_map('strtolower', (array) ($settings['success_statuses'] ?? []));
        $failed = array_map('strtolower', (array) ($settings['failed_statuses'] ?? []));
        $outcome = ! $response->successful() ? 'pending'
            : ($rawStatus !== '' && in_array($rawStatus, $success, true) ? 'successful'
                : ($rawStatus !== '' && in_array($rawStatus, $failed, true) ? 'failed' : 'pending'));
        $providerReference = trim((string) data_get($data, $referencePath, ''));
        $costValue = isset($settings['response_cost_path']) ? data_get($data, $settings['response_cost_path']) : null;
        $costMinor = $this->nairaToMinor($costValue);

        return [
            'outcome' => $outcome,
            'provider_reference' => $providerReference !== '' ? $providerReference : null,
            'message' => substr((string) ($messagePath ? data_get($data, $messagePath, 'SMEPlug response') : 'SMEPlug response'), 0, 500),
            'data' => ['http_status' => $response->status(), 'provider_status' => $rawStatus, 'actual_cost_minor' => $costMinor,
                'response' => ['keys' => array_slice(array_keys($data), 0, 30)]],
        ];
    }

    private function endpointFor(string $serviceCode): string
    {
        $normalized = strtolower($serviceCode);
        return str_contains($normalized, 'data') ? 'data/purchase' : 'airtime/purchase';
    }

    private function requiredInteger(mixed $value, string $field): int
    {
        if (! is_numeric($value) || (string) (int) $value !== (string) $value || (int) $value < 1) {
            throw new RuntimeException('SMEPlug mapping requires a positive integer '.$field.'.');
        }
        return (int) $value;
    }

    private function nairaToMinor(mixed $amount): ?int
    {
        if (! is_numeric($amount)) return null;
        $parts = explode('.', (string) $amount, 2);
        if (! ctype_digit(ltrim($parts[0], '+')) || (isset($parts[1]) && ! ctype_digit($parts[1]))) return null;
        return ((int) $parts[0] * 100) + (int) str_pad(substr($parts[1] ?? '0', 0, 2), 2, '0');
    }
}
