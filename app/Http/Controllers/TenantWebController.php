<?php

namespace App\Http\Controllers;

use App\Services\Accounting\WalletBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class TenantWebController
{
    public function __invoke(Request $request, WalletBalanceService $balances, \App\Services\Subscriptions\SubscriptionEntitlementService $entitlements)
    {
        $tenant = $request->attributes->get('tenant');
        abort_unless($tenant, 404);
        $wallet = DB::table('wallets')->where('owner_type', 'tenant')->where('owner_id', $tenant->id)->where('currency', 'NGN')->first();
        $balance = $wallet ? $balances->availableMinor((int) $wallet->id) : 0;
        $transactions = $wallet ? DB::table('transactions')->where('tenant_id', $tenant->id)->latest()->limit(10)->get() : collect();
        $deposits = $wallet ? DB::table('deposits')->where('wallet_id', $wallet->id)->latest()->limit(10)->get() : collect();
        $paymentGateways = $tenant->status === 'active' && $entitlements->allows((int) $tenant->id, 'vtu_enabled') ? app(\App\Services\Payments\PaymentGatewayRegistry::class)->enabled() : [];
        return view('tenant.dashboard', compact('tenant', 'wallet', 'balance', 'transactions', 'deposits', 'paymentGateways')); 
    }
}
