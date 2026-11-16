<?php

use App\Models\User;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\WalletBalanceService;
use App\Services\Accounting\WalletProvisioningService;
use App\Services\Transactions\VtuTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function createFundedTestWallet(int $amountMinor): int
{
    app(\Database\Seeders\ChartOfAccountsSeeder::class)->run();
    $user = User::create(['name' => 'Test Customer', 'email' => uniqid('test').'@example.test', 'password' => 'long-test-password-123', 'status' => 'active']);
    $walletId = app(WalletProvisioningService::class)->create('user', (int) $user->id);
    $walletAccount = DB::table('wallet_ledger_accounts')->where('wallet_id', $walletId)->first();
    $clearing = DB::table('ledger_accounts')->where('code', 'PAYMENT_CLEARING_NGN')->first();
    app(LedgerService::class)->post('test.funding', 'NGN', [
        ['account_id' => (int) $clearing->id, 'side' => 'debit', 'amount_minor' => $amountMinor],
        ['account_id' => (int) $walletAccount->ledger_account_id, 'side' => 'credit', 'amount_minor' => $amountMinor],
    ], 'TEST-FUND-'.$walletId);
    return $walletId;
}

function createTestServiceAndPrice(int $sellMinor = 1200, int $costMinor = 1000): int
{
    $serviceId = DB::table('service_catalogue')->insertGetId([
        'service_code' => 'TEST-AIRTIME', 'category' => 'airtime', 'name' => 'Test airtime', 'status' => 'active',
        'validation_schema' => json_encode(['phone' => ['required', 'regex:/^080[0-9]{8}$/']]), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('pricing_rules')->insert([
        'tenant_id' => null, 'service_catalogue_id' => $serviceId, 'cost_minor' => $costMinor,
        'sell_minor' => $sellMinor, 'currency' => 'NGN', 'effective_from' => now()->subMinute(),
        'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    return $serviceId;
}

it('reserves funds once, enforces idempotency and refunds only a confirmed failure', function () {
    $walletId = createFundedTestWallet(1500);
    $serviceId = createTestServiceAndPrice();
    $providerId = DB::table('providers')->insertGetId([
        'code' => 'test-provider', 'name' => 'Test Provider', 'adapter' => 'test', 'environment' => 'sandbox',
        'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('provider_services')->insert(['provider_id' => $providerId, 'service_catalogue_id' => $serviceId, 'provider_product_code' => 'TEST-AIRTIME-001', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    $service = app(VtuTransactionService::class);
    $first = $service->reserve($walletId, $serviceId, 'idempotency-1', ['phone' => '08000000000']);
    $again = $service->reserve($walletId, $serviceId, 'idempotency-1', ['phone' => '08000000000']);
    expect($again['id'])->toBe($first['id']);
    expect($first['provider_product_code'])->toBe('TEST-AIRTIME-001');
    expect(app(WalletBalanceService::class)->availableMinor($walletId))->toBe(300);

    expect(fn () => $service->reserve($walletId, $serviceId, 'idempotency-2', ['phone' => '08000000001']))->toThrow(RuntimeException::class);
    expect(fn () => $service->reserve($walletId, $serviceId, 'idempotency-1', ['phone' => '08000000001']))->toThrow(RuntimeException::class);

    $service->markSubmitted((int) $first['id'], $providerId, 'provider-ref-1');
    $service->markPending((int) $first['id'], 'provider-ref-1');
    $service->settleFailed((int) $first['id'], 'Provider confirmed non-fulfilment');
    expect(app(WalletBalanceService::class)->availableMinor($walletId))->toBe(1500);
    expect(DB::table('transactions')->where('id', $first['id'])->value('status'))->toBe('failed');
    expect(fn () => $service->settleFailed((int) $first['id'], 'Duplicate failure callback'))->toThrow(RuntimeException::class);
});

it('settles a confirmed successful transaction without changing its original sale snapshot', function () {
    $walletId = createFundedTestWallet(2000);
    $serviceId = createTestServiceAndPrice(1200, 1000);
    $providerId = DB::table('providers')->insertGetId([
        'code' => 'test-provider-2', 'name' => 'Test Provider', 'adapter' => 'test', 'environment' => 'sandbox',
        'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('provider_services')->insert(['provider_id' => $providerId, 'service_catalogue_id' => $serviceId, 'provider_product_code' => 'TEST-AIRTIME-002', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    $service = app(VtuTransactionService::class);
    $transaction = $service->reserve($walletId, $serviceId, 'idempotency-success', ['phone' => '08000000000']);
    $service->markSubmitted((int) $transaction['id'], $providerId, 'provider-ref-2');
    $service->settleSuccessful((int) $transaction['id'], 1000);
    $fresh = DB::table('transactions')->where('id', $transaction['id'])->first();
    expect($fresh->status)->toBe('successful');
    expect((int) $fresh->sell_minor)->toBe(1200);
    expect((int) $fresh->cost_minor)->toBe(1000);
    expect(app(WalletBalanceService::class)->availableMinor($walletId))->toBe(800);
});


it('binds an idempotency key to the service as well as the customer payload', function () {
    $walletId = createFundedTestWallet(3000);
    $firstServiceId = createTestServiceAndPrice(1200, 1000);
    $secondServiceId = DB::table('service_catalogue')->insertGetId([
        'service_code' => 'TEST-DATA-OTHER', 'category' => 'data', 'name' => 'Other test product', 'status' => 'inactive',
        'validation_schema' => json_encode(['phone' => ['required', 'regex:/^080[0-9]{8}$/']]), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $service = app(VtuTransactionService::class);
    $service->reserve($walletId, $firstServiceId, 'same-key', ['phone' => '08000000000']);
    expect(fn () => $service->reserve($walletId, $secondServiceId, 'same-key', ['phone' => '08000000000']))
        ->toThrow(RuntimeException::class);
});
