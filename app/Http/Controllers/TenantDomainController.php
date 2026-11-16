<?php

namespace App\Http\Controllers;

use App\Models\TenantDomain;
use App\Models\Tenant;
use App\Services\TenantDomainVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class TenantDomainController
{
    public function store(Request $request, int $tenant, TenantDomainVerificationService $verification): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenant);
        abort_unless($request->user()->tenants()->whereKey($tenant->id)->wherePivot('status', 'active')->wherePivot('role', 'tenant-owner')->exists(), 403);
        $data = $request->validate(['hostname' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?!-)[a-z0-9-]+(?:\.[a-z0-9-]+)+$/i', 'unique:tenant_domains,hostname']]);
        $hostname = strtolower(rtrim($data['hostname'], '.'));
        if ($hostname === config('tenancy.base_domain') || str_ends_with($hostname, '.'.config('tenancy.base_domain'))) {
            throw ValidationException::withMessages(['hostname' => 'Use a custom domain, not a platform-owned subdomain.']);
        }
        $domain = TenantDomain::create(['tenant_id' => $tenant->id, 'hostname' => $hostname, 'is_primary' => false, 'tls_status' => 'pending']);
        return response()->json(['data' => ['domain' => $domain, 'dns_challenge' => $verification->issue($domain)]], 201);
    }

    public function verifyPlatformDomain(Request $request, int $domain, \App\Services\AuditLogger $audit): JsonResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:500']]);
        $record = TenantDomain::findOrFail($domain);
        $base = strtolower((string) config('tenancy.base_domain'));
        abort_unless($base && str_ends_with($record->hostname, '.'.$base), 422, 'This is not a platform-owned tenant subdomain.');
        $record->update(['verified_at' => now()]);
        $audit->record('tenant_domain.platform_verified', (int) $record->tenant_id, TenantDomain::class, (string) $record->id, $data['reason'], ['hostname' => $record->hostname]);
        return response()->json(['data' => ['hostname' => $record->hostname, 'verified_at' => $record->fresh()->verified_at, 'tls_status' => $record->tls_status]]);
    }

    public function activateTls(Request $request, int $domain, \App\Services\AuditLogger $audit): JsonResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:500']]);
        $record = TenantDomain::findOrFail($domain);
        abort_unless($record->verified_at, 409, 'DNS ownership must be verified before TLS activation.');
        $record->update(['tls_status' => 'active']);
        $audit->record('tenant_domain.tls_activated', (int) $record->tenant_id, TenantDomain::class, (string) $record->id, $data['reason'], ['hostname' => $record->hostname]);
        return response()->json(['data' => ['hostname' => $record->hostname, 'tls_status' => 'active']]);
    }

    public function verify(Request $request, int $tenant, int $domain, TenantDomainVerificationService $verification): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenant);
        $record = TenantDomain::where('tenant_id', $tenant->id)->findOrFail($domain);
        abort_unless($request->user()->tenants()->whereKey($tenant->id)->wherePivot('status', 'active')->wherePivot('role', 'tenant-owner')->exists(), 403);
        $verification->verify($record);
        return response()->json(['data' => ['hostname' => $record->hostname, 'verified_at' => $record->fresh()->verified_at, 'tls_status' => 'pending'], 'message' => 'DNS ownership verified. TLS must be provisioned before the domain can serve traffic.']);
    }
}
