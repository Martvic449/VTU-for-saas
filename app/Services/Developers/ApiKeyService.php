<?php

namespace App\Services\Developers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class ApiKeyService
{
    /** Secret is returned once; only its SHA-256 hash is persisted. */
    public function create(int $clientId, string $name, array $scopes, ?\DateTimeInterface $expiresAt = null): array
    {
        $prefix = 'vtu_'.Str::lower(Str::random(12));
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $id = DB::table('api_keys')->insertGetId([
            'api_client_id' => $clientId, 'name' => $name, 'prefix' => $prefix,
            'secret_hash' => hash('sha256', $secret), 'scopes' => json_encode(array_values(array_unique($scopes))),
            'expires_at' => $expiresAt, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return ['id' => $id, 'prefix' => $prefix, 'secret' => $prefix.'.'.$secret];
    }

    public function verify(string $presented): ?object
    {
        [$prefix, $secret] = array_pad(explode('.', $presented, 2), 2, null);
        if (! $prefix || ! $secret) return null;
        $key = DB::table('api_keys')->where('prefix', $prefix)->whereNull('revoked_at')->first();
        if (! $key || ($key->expires_at && now()->greaterThan($key->expires_at))) return null;
        if (! hash_equals($key->secret_hash, hash('sha256', $secret))) return null;
        DB::table('api_keys')->where('id', $key->id)->update(['last_used_at' => now(), 'updated_at' => now()]);
        $key->scopes = json_decode($key->scopes ?: '[]', true) ?: [];
        return $key;
    }

    public function revoke(int $clientId, int $keyId): void
    {
        $updated = DB::table('api_keys')->where('id', $keyId)->where('api_client_id', $clientId)->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
        if (! $updated) throw new RuntimeException('API key not found or already revoked.');
    }
}
