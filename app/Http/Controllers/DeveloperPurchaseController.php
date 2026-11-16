<?php
namespace App\Http\Controllers;
use App\Jobs\SubmitVtuTransaction;
use App\Services\Accounting\WalletProvisioningService;
use App\Services\Transactions\VtuTransactionService;
use App\Services\Subscriptions\SubscriptionEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
final class DeveloperPurchaseController
{
    public function __invoke(Request $request, VtuTransactionService $transactions, WalletProvisioningService $wallets, SubscriptionEntitlementService $entitlements): JsonResponse
    {
        $client = $request->attributes->get('api_client');
        abort_unless($client && $client->status === 'active', 401);
        if ($client->tenant_id) {
            abort_unless($entitlements->allows((int) $client->tenant_id, 'api_enabled'), 402, 'The tenant subscription does not include API access or is not active.');
            abort_unless($entitlements->allows((int) $client->tenant_id, 'vtu_enabled'), 402, 'The tenant subscription does not include VTU purchasing or is not active.');
        }
        $data = $request->validate(['service_code' => ['required','string','max:64','exists:service_catalogue,service_code'], 'customer' => ['required','array']]);
        $key = trim((string) $request->header('Idempotency-Key'));
        abort_if($key === '' || strlen($key) > 120, 422, 'A valid Idempotency-Key header is required.');
        $service = DB::table('service_catalogue')->where('service_code', $data['service_code'])->firstOrFail();
        if ($client->tenant_id) $walletId = $wallets->create('tenant', (int) $client->tenant_id, (int) $client->tenant_id);
        else $walletId = $wallets->create('user', (int) $client->user_id);
        try { $tx = $transactions->reserve($walletId, (int) $service->id, $key, $data['customer'], $client->tenant_id ? (int) $client->tenant_id : null); }
        catch (RuntimeException $exception) { return response()->json(['error' => ['code' => 'purchase_rejected', 'message' => $exception->getMessage()]], 422); }
        if ($tx['status'] === 'reserved') SubmitVtuTransaction::dispatch((int) $tx['id'])->afterCommit();
        return response()->json(['data' => ['reference' => $tx['reference'], 'status' => $tx['status'],
            'amount_minor' => (int) $tx['sell_minor'], 'currency' => $tx['currency']]], 202);
    }
}
