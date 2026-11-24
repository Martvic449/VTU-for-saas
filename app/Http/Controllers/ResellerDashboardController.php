<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ResellerDashboardController
{
    private function applications(int $userId)
    {
        return DB::table('tenant_user')
            ->join('tenants', 'tenants.id', '=', 'tenant_user.tenant_id')
            ->leftJoin('tenant_domains', function ($join) {
                $join->on('tenant_domains.tenant_id', '=', 'tenants.id')->where('tenant_domains.is_primary', true);
            })
            ->where('tenant_user.user_id', $userId)
            ->where('tenant_user.role', 'tenant-owner')
            ->select('tenants.id', 'tenants.name', 'tenants.slug', 'tenants.status', 'tenant_domains.hostname', 'tenant_domains.verified_at', 'tenant_domains.tls_status')
            ->orderByDesc('tenants.created_at')->get();
    }

    public function __invoke(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('tenant-owner'), 403);
        $applications = $this->applications((int) $user->id);
        $approved = $user->tenants()
            ->where('tenants.status', 'active')
            ->wherePivot('status', 'active')
            ->wherePivot('role', 'tenant-owner')
            ->get();

        if ($approved->count() === 1) {
            return redirect()->route('tenant.dashboard');
        }

        return view('reseller.dashboard', compact('applications'));
    }

    public function account(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('tenant-owner'), 403);
        $applications = $this->applications((int) $user->id);

        $subscriptions = DB::table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->whereIn('subscriptions.tenant_id', $applications->pluck('id'))
            ->select('subscriptions.*', 'plans.name as plan_name', 'plans.entitlements')
            ->orderByDesc('subscriptions.created_at')->get()
            ->keyBy('tenant_id');

        return view('reseller.account', compact('applications', 'subscriptions'));
    }
}
