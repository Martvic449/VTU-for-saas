<?php

namespace App\Http\Controllers;

use App\Services\Accounting\WalletBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeveloperApiController
{
    public function balance(Request $request, WalletBalanceService $balances): JsonResponse
    {
        $client = $request->attributes->get('api_client');
        $walletQuery = DB::table('wallets')->where('currency', 'NGN')->where('status', 'active');
        if ($client->tenant_id) $walletQuery->where('owner_type', 'tenant')->where('owner_id', $client->tenant_id);
        else $walletQuery->where('owner_type', 'user')->where('owner_id', $client->user_id);
        $wallet = $walletQuery->first();
        abort_unless($wallet, 404, 'API credit wallet is not provisioned.');
        return response()->json(['data' => ['available_minor' => $balances->availableMinor((int) $wallet->id), 'currency' => $wallet->currency]]);
    }

    public function transaction(Request $request, string $reference): JsonResponse
    {
        $client = $request->attributes->get('api_client');
        $query = DB::table('transactions')->where('reference', $reference);
        if ($client->tenant_id) $query->where('tenant_id', $client->tenant_id);
        else {
            $wallet = DB::table('wallets')->where('owner_type', 'user')->where('owner_id', $client->user_id)->first();
            abort_unless($wallet, 404);
            $query->where('wallet_id', $wallet->id);
        }
        $transaction = $query->first();
        abort_unless($transaction, 404);
        return response()->json(['data' => [
            'reference' => $transaction->reference, 'status' => $transaction->status,
            'amount_minor' => (int) $transaction->sell_minor, 'currency' => $transaction->currency,
            'provider_reference' => $transaction->provider_reference,
            'created_at' => $transaction->created_at, 'completed_at' => $transaction->completed_at,
        ]]);
    }
}
