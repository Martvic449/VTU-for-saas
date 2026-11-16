<?php

namespace App\Services\Commissions;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CommissionService
{
    public function postForSuccessfulTransaction(int $transactionId, int $beneficiaryUserId, int $amountMinor, string $version): bool
    {
        $transaction = DB::table('transactions')->where('id', $transactionId)->first();
        if (! $transaction || $transaction->status !== 'successful') throw new RuntimeException('Commissions can only be calculated for successful transactions.');
        if ($amountMinor <= 0 || $amountMinor > (int) $transaction->sell_minor) throw new RuntimeException('Commission amount is invalid.');
        return DB::table('commissions')->insertOrIgnore([
            'tenant_id' => $transaction->tenant_id, 'transaction_id' => $transactionId,
            'beneficiary_user_id' => $beneficiaryUserId, 'amount_minor' => $amountMinor,
            'currency' => $transaction->currency, 'status' => 'pending', 'calculation_version' => $version,
            'created_at' => now(), 'updated_at' => now(),
        ]) === 1;
    }
}
