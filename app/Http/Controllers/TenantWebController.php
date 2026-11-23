<?php

namespace App\\Http\\Controllers;

use App\\Services\\Accounting\\WalletBalanceService;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;

final class TenantWebController
{
    private function ownerTenant(Request $request): object
    {
        $tenant = $request->attributes->get('tenant');
        abort_unless($tenant && $tenant->status === 'active', 404);
        abort_unless($request->user()->tenants()->whereKey($tenant->id)->wherePivot('status', 'active')->wherePivot('role', 'tenant-owner')->exists(), 403);
        return $tenant;
    }

    public function __invoke(Request $request, WalletBalanceService $balances, \\App\\Services\\Subscriptions\\SubscriptionEntitlementService $entitlements)
    {
        $tenant = $this->ownerTenant($request);
        $wallet = DB::table('wallets')->where('owner_type', 'tenant')->where('owner_id', $tenant->id)->where('currency', 'NGN')->first();
        $balance = $wallet ? $balances->availableMinor((int) $wallet->id) : 0;
        $transactions = $wallet ? DB::table('transactions')->where('tenant_id', $tenant->id)->latest()->limit(10)->get() : collect();
        $deposits = $wallet ? DB::table('deposits')->where('wallet_id', $wallet->id)->latest()->limit(10)->get() : collect();
        $paymentGateways = $tenant->status === 'active' && $entitlements->allows((int) $tenant->id, 'vtu_enabled') ? app(\\App\\Services\\Payments\\PaymentGatewayRegistry::class)->enabled() : [];
        $customerCount = DB::table('tenant_customers')->where('tenant_id', $tenant->id)->where('status', 'active')->count();
        $pendingTransactions = DB::table('transactions')->where('tenant_id', $tenant->id)->whereIn('status', ['reserved', 'submitted', 'pending'])->count();
        return view('tenant.dashboard', compact('tenant', 'wallet', 'balance', 'transactions', 'deposits', 'paymentGateways', 'customerCount', 'pendingTransactions'));
    }

    public function customers(Request $request)
    {
        $tenant = $this->ownerTenant($request);
        $customers = DB::table('tenant_customers')
            ->join('users', 'users.id', '=', 'tenant_customers.user_id')
            ->where('tenant_customers.tenant_id', $tenant->id)
            ->select('tenant_customers.id', 'tenant_customers.status', 'tenant_customers.created_at', 'users.name', 'users.email')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = trim((string) $request->query('q'));
                $query->where(function ($where) use ($term) {
                    $where->where('users.name', 'like', '%'.$term.'%')->orWhere('users.email', 'like', '%'.$term.'%');
                });
            })
            ->orderByDesc('tenant_customers.created_at')->paginate(25)->withQueryString();
        return view('tenant.customers', compact('tenant', 'customers'));
    }

    public function services(Request $request)
    {
        $tenant = $this->ownerTenant($request);
        $services = DB::table('service_catalogue')->where('status', 'active')->orderBy('category')->orderBy('name')->get();
        foreach ($services as $service) {
            $rule = DB::table('pricing_rules')->where('service_catalogue_id', $service->id)->where('tenant_id', $tenant->id)->where('status', 'active')->where('effective_from', '<=', now())->where(function ($query) { $query->whereNull('effective_until')->orWhere('effective_until', '>', now()); })->orderByDesc('effective_from')->first();
            $service->reseller_price_minor = $rule?->sell_minor;
            $service->estimated_margin_minor = $rule ? ((int) $rule->sell_minor - (int) $rule->cost_minor) : null;
        }
        return view('tenant.services', compact('tenant', 'services'));
    }

    public function pricing(Request $request)
    {
        $tenant = $this->ownerTenant($request);
        $pricing = DB::table('pricing_rules')
            ->join('service_catalogue', 'service_catalogue.id', '=', 'pricing_rules.service_catalogue_id')
            ->where('pricing_rules.tenant_id', $tenant->id)->where('pricing_rules.status', 'active')
            ->where('pricing_rules.effective_from', '<=', now())
            ->where(function ($query) { $query->whereNull('pricing_rules.effective_until')->orWhere('pricing_rules.effective_until', '>', now()); })
            ->select('service_catalogue.name', 'service_catalogue.category', 'pricing_rules.cost_minor', 'pricing_rules.sell_minor', 'pricing_rules.currency', 'pricing_rules.effective_from', 'pricing_rules.effective_until')
            ->orderBy('service_catalogue.category')->orderBy('service_catalogue.name')->paginate(30);
        return view('tenant.pricing', compact('tenant', 'pricing'));
    }
}
