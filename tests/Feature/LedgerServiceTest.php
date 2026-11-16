<?php

use App\Services\Accounting\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('posts only balanced immutable journals using integer minor units', function () {
    $debit = DB::table('ledger_accounts')->insertGetId(['code' => 'TEST_CASH', 'name' => 'Test cash', 'account_type' => 'asset', 'normal_side' => 'debit', 'currency' => 'NGN', 'created_at' => now(), 'updated_at' => now()]);
    $credit = DB::table('ledger_accounts')->insertGetId(['code' => 'TEST_LIABILITY', 'name' => 'Test liability', 'account_type' => 'liability', 'normal_side' => 'credit', 'currency' => 'NGN', 'created_at' => now(), 'updated_at' => now()]);
    $journal = app(LedgerService::class)->post('test.event', 'NGN', [
        ['account_id' => $debit, 'side' => 'debit', 'amount_minor' => 12345],
        ['account_id' => $credit, 'side' => 'credit', 'amount_minor' => 12345],
    ], 'TEST-JOURNAL-001');
    expect(DB::table('ledger_journals')->where('id', $journal)->exists())->toBeTrue();
    expect((int) DB::table('ledger_entries')->where('ledger_journal_id', $journal)->where('side', 'debit')->sum('amount_minor'))
        ->toBe((int) DB::table('ledger_entries')->where('ledger_journal_id', $journal)->where('side', 'credit')->sum('amount_minor'));
});

it('rejects an unbalanced journal', function () {
    $account = DB::table('ledger_accounts')->insertGetId(['code' => 'TEST_ONE', 'name' => 'Test', 'account_type' => 'asset', 'normal_side' => 'debit', 'currency' => 'NGN', 'created_at' => now(), 'updated_at' => now()]);
    app(LedgerService::class)->post('test.event', 'NGN', [
        ['account_id' => $account, 'side' => 'debit', 'amount_minor' => 100],
        ['account_id' => $account, 'side' => 'credit', 'amount_minor' => 99],
    ], 'TEST-JOURNAL-002');
})->throws(ValidationException::class);
