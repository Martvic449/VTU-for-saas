<?php

namespace App\Services\Providers;

use App\Models\Provider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Read-only access to supplier catalogue/account endpoints documented in the SMEPlug collection. */
final class SmeplugReadOnlyClient
{
    private const BASE_URL = 'https://smeplug.ng/api/v1';
    private const RESOURCES = [
        'networks' => '/networks',
        'data-plans' => '/data/plans',
        'balance' => '/account/balance',
        'devices' => '/devices',
    ];

    public function get(Provider $provider, string $resource): array
    {
        $path = self::RESOURCES[$resource] ?? null;
        if ($path === null) throw new RuntimeException('Unsupported SMEPlug read-only resource.');
        if ($provider->adapter !== 'smeplug') {
            throw new RuntimeException('The selected provider does not use the SMEPlug adapter.');
        }
        $credentials = $provider->credentials_encrypted;
        $token = is_array($credentials) ? (string) ($credentials['api_token'] ?? $credentials['private_key'] ?? '') : '';
        if ($token === '') throw new RuntimeException('SMEPlug private key is not configured.');

        $response = Http::withToken($token)->acceptJson()->connectTimeout(5)->timeout(20)->get(self::BASE_URL.$path);
        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException('SMEPlug '.$resource.' request failed with HTTP '.$response->status().'.');
        }
        return $response->json();
    }
}
