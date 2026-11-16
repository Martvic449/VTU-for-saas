<?php

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Services\Accounting\WalletProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('returns only the active hostname tenant transactions to a tenant member', function () {
    $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN']);
    $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN']);
    TenantDomain::create(['tenant_id' => $tenantA->id, 'hostname' => 'a.example.test', 'is_primary' => true, 'verified_at' => now(), 'tls_status' => 'active']);
    TenantDomain::create(['tenant_id' => $tenantB->id, 'hostname' => 'b.example.test', 'is_primary' => true, 'verified_at' => now(), 'tls_status' => 'active']);
    $owner = User::create(['name' => 'Tenant A Owner', 'email' => 'owner-a@example.test', 'password' => 'long-test-password-123', 'status' => 'active']);
    $tenantA->users()->attach($owner->id, ['role' => 'tenant-owner', 'status' => 'active']);
    $walletA = app(WalletProvisioningService::class)->create('tenant', (int) $tenantA->id, (int) $tenantA->id);
    $walletB = app(WalletProvisioningService::class)->create('tenant', (int) $tenantB->id, (int) $tenantB->id);
    $serviceId = DB::table('service_catalogue')->insertGetId(['service_code' => 'TEST-ISO', 'category' => 'airtime', 'name' => 'Isolation test', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    foreach ([[$tenantA, $walletA, 'A-REF'], [$tenantB, $walletB, 'B-REF']] as [$tenant, $wallet, $reference]) {
        DB::table('transactions')->insert([
            'tenant_id' => $tenant->id, 'wallet_id' => $wallet, 'service_catalogue_id' => $serviceId,
            'reference' => $reference, 'idempotency_key' => 'key-'.$reference, 'request_fingerprint' => hash('sha256', $reference),
            'status' => 'successful', 'sell_minor' => 100, 'cost_minor' => 90, 'currency' => 'NGN',
            'input_snapshot' => '{}', 'price_snapshot' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $this->actingAs($owner, 'sanctum')->get('http://a.example.test/api/v1/tenant/transactions')
        ->assertOk()->assertJsonFragment(['reference' => 'A-REF'])->assertJsonMissing(['reference' => 'B-REF']);
});
