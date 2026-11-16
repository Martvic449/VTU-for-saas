<?php

namespace App\Services\Accounting;

use Illuminate\Support\Facades\DB;

final class WalletProvisioningService
{
    public function create(string $ownerType, int $ownerId, ?int $tenantId = null, string $currency = 'NGN'): int
    {
        if (! in_array($ownerType, ['user', 'tenant', 'tenant_customer'], true) || $ownerId <= 0 || ($ownerType === 'tenant' && $tenantId !== $ownerId) || ($ownerType === 'user' && $tenantId !== null) || ($ownerType === 'tenant_customer' && ($tenantId === null || ! DB::table('tenant_customers')->where('id', $ownerId)->where('tenant_id', $tenantId)->exists()))) {
            throw new \InvalidArgumentException('Wallet owner identity is invalid.');
        }
        return DB::transaction(function () use ($ownerType, $ownerId, $tenantId, $currency): int {
            $existing = DB::table('wallets')->where('owner_type', $ownerType)->where('owner_id', $ownerId)->where('currency', $currency)->first();
            if ($existing) return (int) $existing->id;

            $walletId = DB::table('wallets')->insertGetId([
                'tenant_id' => $tenantId, 'user_id' => $ownerType === 'user' ? $ownerId : ($ownerType === 'tenant_customer' ? (int) DB::table('tenant_customers')->where('id', $ownerId)->value('user_id') : null),
                'owner_type' => $ownerType, 'owner_id' => $ownerId, 'currency' => $currency,
                'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $accountId = DB::table('ledger_accounts')->insertGetId([
                'code' => 'WALLET_'.$walletId, 'name' => ucfirst($ownerType).' wallet '.$ownerId,
                'account_type' => 'liability', 'normal_side' => 'credit', 'currency' => $currency,
                'is_control_account' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('wallet_ledger_accounts')->insert([
                'wallet_id' => $walletId, 'ledger_account_id' => $accountId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return $walletId;
        }, 3);
    }
}
