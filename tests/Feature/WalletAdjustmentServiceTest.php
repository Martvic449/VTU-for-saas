<?php
use App\Models\User;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\WalletAdjustmentService;
use App\Services\Accounting\WalletBalanceService;
use App\Services\Accounting\WalletProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
uses(RefreshDatabase::class);

it('requires a different approver and posts an approved wallet credit to the ledger', function () {
    app(\Database\Seeders\ChartOfAccountsSeeder::class)->run();
    $requester=User::create(['name'=>'Finance Maker','email'=>'maker@example.test','password'=>'long-test-password-123','status'=>'active']);
    $approver=User::create(['name'=>'Finance Checker','email'=>'checker@example.test','password'=>'long-test-password-123','status'=>'active']);
    $walletId=app(WalletProvisioningService::class)->create('user',(int)$requester->id);
    $request=app(WalletAdjustmentService::class)->request($walletId,2500,'credit','Approved manual service credit','CASE-100',(int)$requester->id);
    expect(app(WalletBalanceService::class)->availableMinor($walletId))->toBe(0);
    expect(fn()=>app(WalletAdjustmentService::class)->approve($request,(int)$requester->id,'Self approval attempt'))->toThrow(RuntimeException::class);
    $journal=app(WalletAdjustmentService::class)->approve($request,(int)$approver->id,'Verified evidence and approved');
    expect($journal)->toBeGreaterThan(0)
        ->and(app(WalletBalanceService::class)->availableMinor($walletId))->toBe(2500)
        ->and(DB::table('wallet_adjustments')->where('id',$request)->value('status'))->toBe('approved');
});
