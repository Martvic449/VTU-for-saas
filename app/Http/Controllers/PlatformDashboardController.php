<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PlatformDashboardController
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['super-admin', 'operations-admin', 'finance-admin', 'kyc-reviewer']), 403);
        $counts = [
            'users' => DB::table('users')->count(),
            'tenants_active' => DB::table('tenants')->where('status', 'active')->count(),
            'tenants_pending' => DB::table('tenants')->where('status', 'pending')->count(),
            'transactions_total' => DB::table('transactions')->count(),
            'transactions_pending' => DB::table('transactions')->whereIn('status', ['submitted', 'pending'])->count(),
            'transactions_successful' => DB::table('transactions')->where('status', 'successful')->count(),
            'transactions_failed' => DB::table('transactions')->where('status', 'failed')->count(),
            'deposits_pending' => DB::table('deposits')->whereIn('status', ['initiated', 'pending', 'under_review'])->count(),
            'webhooks_pending' => DB::table('webhook_deliveries')->whereIn('status', ['pending', 'retrying'])->count(),
        ];
        return response()->json(['data' => $counts]);
    }
}
