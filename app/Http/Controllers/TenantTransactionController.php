<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class TenantTransactionController
{
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        abort_unless($tenant, 404);
        abort_unless($request->user()->tenants()->whereKey($tenant->id)->wherePivot('status', 'active')->exists(), 403);
        $records = DB::table('transactions')->where('tenant_id', $tenant->id)
            ->select(['reference', 'status', 'sell_minor', 'cost_minor', 'currency', 'created_at', 'completed_at'])
            ->latest()->paginate(25);
        return response()->json(['data' => $records]);
    }
}
