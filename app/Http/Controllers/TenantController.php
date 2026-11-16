<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\AuditLogger;
use App\Services\Accounting\WalletProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

final class TenantController
{
    public function store(Request $request, AuditLogger $audit, WalletProvisioningService $wallets): JsonResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'alpha_dash:ascii', 'min:3', 'max:50', 'unique:tenants,slug', Rule::notIn(config('tenancy.reserved_slugs'))],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'timezone' => ['sometimes', 'timezone'],
            'currency' => ['sometimes', 'size:3', 'in:NGN'],
        ]);
        $tenant = DB::transaction(function () use ($data, $wallets, $audit): Tenant {
            $tenant = Tenant::create([
                'name' => $data['name'], 'slug' => strtolower($data['slug']), 'status' => 'pending',
                'timezone' => $data['timezone'] ?? 'Africa/Lagos', 'currency' => strtoupper($data['currency'] ?? 'NGN'),
            ]);
            $wallets->create('tenant', (int) $tenant->id, (int) $tenant->id);
            if (! empty($data['owner_user_id'])) {
                $owner = \App\Models\User::findOrFail($data['owner_user_id']);
                abort_unless($owner->status === 'active', 422, 'Tenant owner account must be active.');
                $owner->assignRole('tenant-owner');
                $tenant->users()->syncWithoutDetaching([$owner->id => ['role' => 'tenant-owner', 'status' => 'active']]);
            }
            $baseDomain = strtolower((string) config('tenancy.base_domain'));
            if ($baseDomain && $baseDomain !== 'localhost') {
                TenantDomain::create(['tenant_id' => $tenant->id, 'hostname' => $tenant->slug.'.'.$baseDomain, 'is_primary' => true, 'tls_status' => 'pending']);
            }
            $audit->record('tenant.created', (int) $tenant->id, Tenant::class, (string) $tenant->id, 'Tenant provisioned by platform administrator', ['slug' => $tenant->slug]);
            return $tenant->load('domains');
        }, 3);
        return response()->json(['data' => $tenant->load('domains'), 'message' => 'Tenant created in pending status. Complete verification and approval before activation.'], 201);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['super-admin', 'operations-admin']), 403);
        $tenants = Tenant::query()->with('domains')->latest()->paginate(25);
        return response()->json(['data' => $tenants]);
    }

    public function updateStatus(Request $request, int $tenant, AuditLogger $audit): JsonResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended', 'pending'])], 'reason' => ['required', 'string', 'min:8', 'max:500']]);
        $record = Tenant::findOrFail($tenant);
        $before = $record->status;
        $record->update(['status' => $data['status']]);
        $audit->record('tenant.status_changed', (int) $record->id, Tenant::class, (string) $record->id, $data['reason'], ['before' => $before, 'after' => $record->status]);
        return response()->json(['data' => $record->fresh(), 'message' => 'Tenant status updated.']);
    }

    public function current(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        abort_unless($tenant, 404);
        $user = $request->user();
        abort_unless($user && $user->tenants()->whereKey($tenant->id)->wherePivot('status', 'active')->exists(), 403);
        return response()->json(['data' => $tenant->only(['id', 'name', 'slug', 'status', 'timezone', 'currency'])]);
    }
}
