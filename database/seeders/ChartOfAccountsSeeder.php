<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['code' => 'PAYMENT_CLEARING_NGN', 'name' => 'Payment clearing', 'account_type' => 'asset', 'normal_side' => 'debit'],
            ['code' => 'PROVIDER_FLOAT_NGN', 'name' => 'Provider float', 'account_type' => 'asset', 'normal_side' => 'debit'],
            ['code' => 'PROVIDER_PAYABLE_NGN', 'name' => 'Provider payable', 'account_type' => 'liability', 'normal_side' => 'credit'],
            ['code' => 'SERVICE_REVENUE_NGN', 'name' => 'Service revenue', 'account_type' => 'income', 'normal_side' => 'credit'],
            ['code' => 'PROVIDER_COST_NGN', 'name' => 'Provider cost', 'account_type' => 'expense', 'normal_side' => 'debit'],
            ['code' => 'PLATFORM_SUSPENSE_NGN', 'name' => 'Platform suspense', 'account_type' => 'liability', 'normal_side' => 'credit'],
            ['code' => 'WALLET_ADJUSTMENT_EXPENSE_NGN', 'name' => 'Wallet adjustment expense', 'account_type' => 'expense', 'normal_side' => 'debit'],
            ['code' => 'WALLET_ADJUSTMENT_RECOVERY_NGN', 'name' => 'Wallet adjustment recovery', 'account_type' => 'income', 'normal_side' => 'credit'],
        ];
        foreach ($accounts as $account) {
            DB::table('ledger_accounts')->updateOrInsert(['code' => $account['code']], $account + ['currency' => 'NGN', 'is_control_account' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
