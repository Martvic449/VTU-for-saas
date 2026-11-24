<?php

namespace App\Services\Subscriptions;

use Illuminate\Support\Facades\DB;

final class SubscriptionEntitlementService
{
    public function allows(int $tenantId, string $feature): bool
    {
        $hasAnySubscription = DB::table('subscriptions')->where('tenant_id', $tenantId)->exists();

        // Existing approved tenants created before subscription plans were
        // configured receive a deliberately limited, zero-cost sandbox plan.
        // Never override a pending, expired, suspended or paid subscription.
        if (! $hasAnySubscription) {
            $tenantIsActive = DB::table('tenants')->where('id', $tenantId)->where('status', 'active')->exists();
            if ($tenantIsActive) {
                DB::transaction(function () use ($tenantId): void {
                    if (DB::table('subscriptions')->where('tenant_id', $tenantId)->exists()) return;

                    $now = now();
                    $planId = DB::table('plans')->where('code', 'sandbox-starter')->value('id');
                    if (! $planId) {
                        $planId = DB::table('plans')->insertGetId([
                            'code' => 'sandbox-starter',
                            'name' => 'Sandbox Starter',
                            'price_minor' => 0,
                            'currency' => 'NGN',
                            'billing_interval' => 'month',
                            'entitlements' => json_encode([
                                'vtu_enabled' => true,
                                'api_enabled' => true,
                                'sandbox_only' => true,
                            ]),
                            'status' => 'active',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    DB::table('subscriptions')->insert([
                        'tenant_id' => $tenantId,
                        'plan_id' => $planId,
                        'status' => 'active',
                        'starts_at' => $now,
                        'ends_at' => null,
                        'grace_ends_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }, 3);
            }
        }

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
