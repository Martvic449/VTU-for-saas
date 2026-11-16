<?php

namespace App\Services;

use App\Models\TenantDomain;
use Illuminate\Support\Str;
use RuntimeException;

final class TenantDomainVerificationService
{
    public function issue(TenantDomain $domain): array
    {
        $token = Str::random(48);
        $domain->forceFill([
            'verification_token_hash' => hash('sha256', $token),
            'verification_expires_at' => now()->addHours(24),
            'verified_at' => null,
        ])->save();
        return ['record_type' => 'TXT', 'record_name' => '_vtu-verify.'.$domain->hostname, 'record_value' => 'vtu-verification='.$token, 'expires_at' => $domain->verification_expires_at];
    }

    public function verify(TenantDomain $domain): void
    {
        if (! $domain->verification_token_hash || ! $domain->verification_expires_at || now()->greaterThan($domain->verification_expires_at)) {
            throw new RuntimeException('Domain verification challenge is missing or expired.');
        }
        $records = @dns_get_record('_vtu-verify.'.$domain->hostname, DNS_TXT) ?: [];
        $expected = 'vtu-verification=';
        foreach ($records as $record) {
            $value = (string) ($record['txt'] ?? '');
            if (str_starts_with($value, $expected)) {
                $token = substr($value, strlen($expected));
                if (hash_equals($domain->verification_token_hash, hash('sha256', $token))) {
                    $domain->forceFill(['verified_at' => now(), 'verification_token_hash' => null, 'verification_expires_at' => null])->save();
                    return;
                }
            }
        }
        throw new RuntimeException('The expected DNS TXT verification record was not found.');
    }
}
