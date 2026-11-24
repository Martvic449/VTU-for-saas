<?php

namespace App\Services\Providers;

use App\Models\Provider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Read-only VTpass catalogue client.
 * Uses the official API-key/public-key GET authentication contract and never calls purchase endpoints.
 */
final class VtpassReadOnlyCatalogueClient
{
    public function get(Provider $provider, string $resource, ?string $identifier = null): array
    {
        if ($provider->adapter !== 'vtpass') {
            throw new RuntimeException('The selected provider does not use the VTpass adapter.');
        }
        if ($provider->environment !== 'sandbox') {
            throw new RuntimeException('VTpass catalogue discovery is restricted to sandbox credentials.');
        }

        $credentials = $provider->credentials_encrypted;
        $apiKey = is_array($credentials) ? (string) ($credentials['api_key'] ?? '') : '';
        $publicKey = is_array($credentials) ? (string) ($credentials['public_key'] ?? '') : '';
        if ($apiKey === '' || $publicKey === '') {
            throw new RuntimeException('VTpass catalogue discovery requires the sandbox API key and public key.');
        }

        $base = rtrim((string) data_get($provider->settings, 'base_url', 'https://sandbox.vtpass.com/api'), '/');
        if ($base !== 'https://sandbox.vtpass.com/api') {
            throw new RuntimeException('VTpass catalogue discovery must use the official sandbox API endpoint.');
        }

        $query = [];
        $path = match ($resource) {
            'service-categories' => 'service-categories',
            'services' => 'services',
            'service-variations' => 'service-variations',
            default => throw new RuntimeException('Unsupported VTpass catalogue resource.'),
        };

        if ($resource === 'services') {
            $this->assertIdentifier($identifier);
            $query['identifier'] = $identifier;
        } elseif ($resource === 'service-variations') {
            $this->assertIdentifier($identifier);
            $query['serviceID'] = $identifier;
        }

        try {
            $response = Http::withHeaders([
                'api-key' => $apiKey,
                'public-key' => $publicKey,
            ])->acceptJson()->connectTimeout(5)->timeout(20)->get($base.'/'.$path, $query);
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            throw new RuntimeException('VTpass catalogue connection failed. No catalogue data was imported.', 0, $exception);
        }

        $payload = $response->json();
        if (! $response->successful() || ! is_array($payload)) {
            throw new RuntimeException('VTpass catalogue request failed with HTTP '.$response->status().'.');
        }
        $responseCode = (string) ($payload['response_description'] ?? $payload['code'] ?? '');
        if ($responseCode !== '' && ! in_array($responseCode, ['000', '0000'], true)) {
            throw new RuntimeException('VTpass rejected the catalogue request: '.substr((string) ($payload['response_description'] ?? $responseCode), 0, 200));
        }
        if (! array_key_exists('content', $payload)) {
            throw new RuntimeException('VTpass returned no catalogue content. No items were imported.');
        }

        return $payload;
    }

    private function assertIdentifier(?string $identifier): void
    {
        if (! is_string($identifier) || ! preg_match('/^[A-Za-z0-9_-]{1,100}$/', $identifier)) {
            throw new RuntimeException('A valid VTpass category identifier or serviceID is required.');
        }
    }
}
