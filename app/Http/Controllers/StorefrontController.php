<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class StorefrontController
{
    public function __invoke(Request $request)
    {
        $tenant = $request->attributes->get('tenant');
        if ($tenant && $request->user() && $request->user()->tenants()->whereKey($tenant->id)->wherePivot('status', 'active')->exists()) return redirect()->route('tenant.dashboard');
        if ($tenant && $request->user() && DB::table('tenant_customers')->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->where('status', 'active')->exists()) return redirect()->route('tenant.customer.dashboard');
        if (! $tenant && $request->user()) return redirect()->route('dashboard');
        if (! $tenant) return view('welcome');

        $services = app(\App\Services\Subscriptions\SubscriptionEntitlementService::class)->allows((int) $tenant->id, 'vtu_enabled') && $tenant->status === 'active'
            ? DB::table('service_catalogue')->where('status', 'active')->orderBy('category')->orderBy('name')->get() : collect();
        $visible = collect();
        foreach ($services as $service) {
            $mapping = DB::table('provider_services')->join('providers', 'providers.id', '=', 'provider_services.provider_id')
                ->where('provider_services.service_catalogue_id', $service->id)->where('provider_services.status', 'active')
                ->where('providers.status', 'active')->where('providers.environment', config('providers.environment', 'sandbox'))
                ->orderBy('provider_services.priority')->orderBy('provider_services.id')->select('provider_services.id')->first();
            if (! $mapping) continue;
            $prices = DB::table('pricing_rules')->where('service_catalogue_id', $service->id)->where('status', 'active')
                ->where(function ($q) use ($mapping) { $q->where('provider_service_id', $mapping->id)->orWhereNull('provider_service_id'); })
                ->where('effective_from', '<=', now())->where(function ($q) { $q->whereNull('effective_until')->orWhere('effective_until', '>', now()); });
            $base = (clone $prices)->whereNull('tenant_id')->orderByRaw('CASE WHEN provider_service_id IS NULL THEN 1 ELSE 0 END')->orderByDesc('effective_from')->first();
            $tenantPrice = (clone $prices)->where('tenant_id', $tenant->id)->orderByRaw('CASE WHEN provider_service_id IS NULL THEN 1 ELSE 0 END')->orderByDesc('effective_from')->first();
            $price = $tenantPrice ?: $base;
            if ($price) { $service->sell_minor = (int) $price->sell_minor; $visible->push($service); }
        }
        return view('tenant.storefront', ['tenant' => $tenant, 'services' => $visible]);
    }
}
