<?php
use App\Models\Tenant;
use App\Models\User;
use App\Services\Accounting\WalletProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
uses(RefreshDatabase::class);
it('creates a tenant-scoped customer wallet linked to the customer and tenant', function () {
    app(\Database\Seeders\ChartOfAccountsSeeder::class)->run();
    $tenant = Tenant::create(['name' => 'Reseller One', 'slug' => 'reseller-one', 'status' => 'active', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN']);
    $user = User::create(['name' => 'Reseller Customer', 'email' => 'tenant-customer@example.test', 'password' => 'long-test-password-123', 'status' => 'active']);
    $customerId = DB::table('tenant_customers')->insertGetId(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    $walletId = app(WalletProvisioningService::class)->create('tenant_customer', $customerId, (int) $tenant->id);
    $wallet = DB::table('wallets')->where('id', $walletId)->first();
    expect($wallet->owner_type)->toBe('tenant_customer')->and((int) $wallet->tenant_id)->toBe((int) $tenant->id)->and((int) $wallet->user_id)->toBe((int) $user->id);
});
