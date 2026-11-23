<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ResellerDashboardController
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('tenant-owner'), 403);

        $applications = DB::table('tenant_user')
            ->join('tenants', 'tenants.id', '=', 'tenant_user.tenant_id')
            ->leftJoin('tenant_domains', function ($join) {
                $join->on('tenant_domains.tenant_id', '=', 'tenants.id')
                    ->where('tenant_domains.is_primary', true);
            })
            ->where('tenant_user.user_id', $user->id)
            ->where('tenant_user.role', 'tenant-owner')
            ->select(
                'tenants.id', 'tenants.name', 'tenants.slug', 'tenants.status',
                'tenant_domains.hostname', 'tenant_domains.verified_at',
                'tenant_domains.tls_status'
            )
            ->orderByDesc('tenants.created_at')
            ->get();

        return view('reseller.dashboard', compact('applications'));
    }
}
