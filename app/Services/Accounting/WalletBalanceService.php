<?php

namespace App\Services\Accounting;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class WalletBalanceService
{
    public function availableMinor(int $walletId): int
    {
        $mapping = DB::table('wallet_ledger_accounts')->where('wallet_id', $walletId)->first();
        if (! $mapping) throw new RuntimeException('Wallet ledger account is not configured.');
        $account = DB::table('ledger_accounts')->where('id', $mapping->ledger_account_id)->first();
        if (! $account) throw new RuntimeException('Wallet ledger account is missing.');
        $debits = (int) DB::table('ledger_entries')->where('ledger_account_id', $account->id)->where('side', 'debit')->sum('amount_minor');
        $credits = (int) DB::table('ledger_entries')->where('ledger_account_id', $account->id)->where('side', 'credit')->sum('amount_minor');
        $balance = $account->normal_side === 'credit' ? $credits - $debits : $debits - $credits;
        if ($balance < 0) throw new RuntimeException('Wallet ledger balance is negative; finance reconciliation is required.');
        return $balance;
    }
}
