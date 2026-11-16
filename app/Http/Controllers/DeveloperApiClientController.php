<?php

namespace App\Http\Controllers;

use App\Services\Developers\ApiKeyService;
use App\Services\AuditLogger;
use App\Services\Subscriptions\SubscriptionEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class DeveloperApiClientController
{
    public function store(Request $request, int $tenant, SubscriptionEntitlementService $entitlements, AuditLogger $audit): JsonResponse
    {
        $tenant = \App\Models\Tenant::findOrFail($tenant);
        abort_unless($request->user()->tenants()->whereKey($tenant->id)->wherePivot('status', 'active')->wherePivot('role', 'tenant-owner')->exists(), 403);
        abort_unless($tenant->status === 'active', 409, 'Tenant must be approved before API clients can be created.');
        abort_unless($entitlements->allows((int) $tenant->id, 'api_enabled'), 402, 'The tenant subscription does not include API access or is not active.');
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'environment' => ['required', Rule::in(['sandbox'])], 'ip_allowlist' => ['sometimes', 'array'], 'ip_allowlist.*' => ['ip']]);
        $id = DB::table('api_clients')->insertGetId([
            'tenant_id' => $tenant->id, 'user_id' => $request->user()->id, 'name' => $data['name'],
            'environment' => $data['environment'], 'scopes' => json_encode(['transactions:read', 'transactions:write', 'balance:read']),
            'ip_allowlist' => isset($data['ip_allowlist']) ? json_encode(array_values(array_unique($data['ip_allowlist']))) : null,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $audit->record('api_client.created', (int) $tenant->id, 'api_clients', (string) $id, 'Tenant API client created', ['environment' => 'sandbox']);
        return response()->json(['data' => DB::table('api_clients')->where('id', $id)->first()], 201);
    }

    public function createKey(Request $request, int $tenant, int $client, ApiKeyService $keys, SubscriptionEntitlementService $entitlements, AuditLogger $audit): JsonResponse
    {
        $tenant = \App\Models\Tenant::findOrFail($tenant);
        abort_unless($request->user()->tenants()->whereKey($tenant->id)->wherePivot('status', 'active')->wherePivot('role', 'tenant-owner')->exists(), 403);
        abort_unless($tenant->status === 'active', 409, 'Tenant is not active.');
        abort_unless($entitlements->allows((int) $tenant->id, 'api_enabled'), 402, 'The tenant subscription does not include API access or is not active.');
        $apiClient = DB::table('api_clients')->where('id', $client)->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($apiClient, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'scopes' => ['required', 'array', 'min:1'], 'scopes.*' => [Rule::in(['transactions:read', 'transactions:write', 'balance:read'])]]);
        $created = $keys->create($client, $data['name'], $data['scopes']);
        $audit->record('api_key.created', (int) $tenant->id, 'api_keys', (string) $created['id'], 'Tenant API key created; secret returned once', ['client_id' => $client, 'scopes' => $data['scopes']]);
        return response()->json(['data' => $created], 201);
    }

    public function revokeKey(Request $request, int $tenant, int $client, int $key, ApiKeyService $keys, AuditLogger $audit): JsonResponse
    {
        $tenant = \App\Models\Tenant::findOrFail($tenant);
        abort_unless($request->user()->tenants()->whereKey($tenant->id)->wherePivot('status', 'active')->wherePivot('role', 'tenant-owner')->exists(), 403);
        abort_unless(DB::table('api_clients')->where('id', $client)->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->exists(), 404);
        $keys->revoke($client, $key);
        $audit->record('api_key.revoked', (int) $tenant->id, 'api_keys', (string) $key, 'Tenant API key revoked', ['client_id' => $client]);
        return response()->json(['message' => 'API key revoked.']);
    }
}
