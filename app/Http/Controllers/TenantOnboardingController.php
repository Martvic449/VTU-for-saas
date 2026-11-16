<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Services\Accounting\WalletProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class TenantOnboardingController
{
    public function __invoke(Request $request, WalletProvisioningService $wallets): JsonResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'alpha_dash:ascii', 'min:3', 'max:50', 'unique:tenants,slug', Rule::notIn(config('tenancy.reserved_slugs'))],
            'owner_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:users,phone'],
        ]);
        $tenant = DB::transaction(function () use ($data, $wallets): Tenant {
            $user = User::create(['name' => $data['owner_name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null, 'password' => $data['password'], 'status' => 'active']);
            $tenant = Tenant::create(['name' => $data['business_name'], 'slug' => strtolower($data['slug']), 'status' => 'pending', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN']);
            $user->assignRole('tenant-owner');
            $tenant->users()->attach($user->id, ['role' => 'tenant-owner', 'status' => 'active']);
            $wallets->create('tenant', (int) $tenant->id, (int) $tenant->id);
            $wallets->create('user', (int) $user->id);
            $base = strtolower((string) config('tenancy.base_domain'));
            if ($base && $base !== 'localhost') {
                TenantDomain::create(['tenant_id' => $tenant->id, 'hostname' => $tenant->slug.'.'.$base, 'is_primary' => true, 'tls_status' => 'pending']);
            }
            return $tenant;
        }, 3);
        $tenant->users()->first()?->sendEmailVerificationNotification();
        return response()->json(['data' => ['tenant' => $tenant->load('domains'), 'status' => 'pending_review', 'verification_required' => true], 'message' => 'Your reseller application has been received. Verify the owner email; access is enabled after platform review and domain activation.'], 201);
    }
}
