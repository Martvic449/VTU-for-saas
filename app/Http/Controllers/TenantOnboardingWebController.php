<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Services\Accounting\WalletProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class TenantOnboardingWebController
{
    public function show() { return view('auth.register-tenant'); }

    public function store(Request $request, WalletProvisioningService $wallets): RedirectResponse
    {
        $data = $request->validate([
            'business_name' => ['required','string','max:160'],
            'slug' => ['required','alpha_dash:ascii','min:3','max:50','unique:tenants,slug',Rule::notIn(config('tenancy.reserved_slugs'))],
            'owner_name' => ['required','string','max:160'],
            'email' => ['required','email:rfc','max:190','unique:users,email'],
            'phone' => ['nullable','string','max:30','unique:users,phone'],
            'password' => ['required','string','min:12','confirmed'],
        ]);
        $owner = DB::transaction(function () use ($data, $wallets): User {
            $user = User::create(['name' => $data['owner_name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null, 'password' => $data['password'], 'status' => 'active']);
            $user->assignRole('tenant-owner');
            $tenant = Tenant::create(['name' => $data['business_name'], 'slug' => strtolower($data['slug']), 'status' => 'pending', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN']);
            $tenant->users()->attach($user->id, ['role' => 'tenant-owner', 'status' => 'active']);
            $wallets->create('tenant', (int) $tenant->id, (int) $tenant->id);
            $wallets->create('user', (int) $user->id);
            $base = strtolower((string) config('tenancy.base_domain'));
            if ($base && $base !== 'localhost') TenantDomain::create(['tenant_id' => $tenant->id, 'hostname' => $tenant->slug.'.'.$base, 'is_primary' => true, 'verified_at' => now(), 'tls_status' => 'pending']);
            return $user;
        }, 3);
        Auth::login($owner);
        $request->session()->regenerate();
        $owner->sendEmailVerificationNotification();
        return redirect()->route('verification.notice')->with('status', 'Your reseller application has been submitted for review. Verify your email; platform access will be enabled after tenant approval and automatic subdomain and HTTPS activation.');
    }
}
