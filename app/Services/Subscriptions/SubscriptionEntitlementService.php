<?php

namespace App\Services\Subscriptions;

use Illuminate\Support\Facades\DB;

final class SubscriptionEntitlementService
{
    public function allows(int $tenantId, string $feature): bool
    {
        $subscription = DB::table('subscriptions')->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('subscriptions.tenant_id', $tenantId)
            ->where('plans.status', 'active')
            ->where(function ($query) {
                $query->where(function ($active) {
                    $active->whereIn('subscriptions.status', ['active', 'trial'])
                        ->where(function ($starts) { $starts->whereNull('subscriptions.starts_at')->orWhere('subscriptions.starts_at', '<=', now()); })
                        ->where(function ($dates) { $dates->whereNull('subscriptions.ends_at')->orWhere('subscriptions.ends_at', '>', now()); });
                })->orWhere(function ($grace) {
                    $grace->where('subscriptions.status', 'grace')->where('subscriptions.grace_ends_at', '>', now());
                });
            })
            ->orderByDesc('subscriptions.created_at')->select('plans.entitlements')->first();
        if (! $subscription) return false;
        $entitlements = json_decode($subscription->entitlements ?: '{}', true) ?: [];
        return (bool) ($entitlements[$feature] ?? false);
    }
}
