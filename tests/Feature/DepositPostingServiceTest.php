<?php

use App\Models\User;
use App\Services\Accounting\WalletBalanceService;
use App\Services\Accounting\WalletProvisioningService;
use App\Services\Payments\DepositPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('credits a verified deposit once when the gateway repeats its webhook', function () {
    app(\Database\Seeders\ChartOfAccountsSeeder::class)->run();
    $user = User::create(['name' => 'Deposit Customer', 'email' => uniqid('deposit').'@example.test', 'password' => 'long-test-password-123', 'status' => 'active']);
    $walletId = app(WalletProvisioningService::class)->create('user', (int) $user->id);
    DB::table('deposits')->insert([
        'wallet_id' => $walletId, 'reference' => 'DEP-TEST-0001', 'gateway' => 'test-gateway',
        'gateway_reference' => 'gateway-ref-1', 'amount_minor' => 25000, 'currency' => 'NGN',
        'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $verified = ['status' => 'successful', 'amount_minor' => 25000, 'currency' => 'NGN', 'reference' => 'DEP-TEST-0001'];
    $posting = app(DepositPostingService::class);
    expect($posting->recordVerifiedSuccess('test-gateway', 'event-1', 'DEP-TEST-0001', $verified))->toBeTrue();
    expect($posting->recordVerifiedSuccess('test-gateway', 'event-1', 'DEP-TEST-0001', $verified))->toBeFalse();
    expect($posting->recordVerifiedSuccess('test-gateway', 'event-2', 'DEP-TEST-0001', $verified))->toBeFalse();
    expect(app(WalletBalanceService::class)->availableMinor($walletId))->toBe(25000);
    expect(DB::table('ledger_journals')->where('event_type', 'wallet.deposit')->count())->toBe(1);
});
