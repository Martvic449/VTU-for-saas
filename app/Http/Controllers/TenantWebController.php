<?php

namespace App\Http\Controllers;

use App\Services\Accounting\WalletBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Jobs\SubmitVtuTransaction;
use App\Services\Transactions\VtuTransactionService;
use RuntimeException;

final class TenantWebController
{
    private function ownerTenant(Request $request): object
    {
        $tenant = $request->attributes->get('tenant');
        abort_unless($tenant && $tenant->status === 'active', 404);
        abort_unless($request->user()->tenants()->whereKey($tenant->id)->wherePivot('status', 'active')->wherePivot('role', 'tenant-owner')->exists(), 403);
        return $tenant;
    }

    public function __invoke(Request $request, WalletBalanceService $balances, \App\Services\Subscriptions\SubscriptionEntitlementService $entitlements)
    {
        $tenant = $this->ownerTenant($request);
        $wallet = DB::table('wallets')->where('owner_type', 'tenant')->where('owner_id', $tenant->id)->where('currency', 'NGN')->first();
        $balance = $wallet ? $balances->availableMinor((int) $wallet->id) : 0;
        $transactions = $wallet ? DB::table('transactions')->where('tenant_id', $tenant->id)->latest()->limit(10)->get() : collect();
        $deposits = $wallet ? DB::table('deposits')->where('wallet_id', $wallet->id)->latest()->limit(10)->get() : collect();
        $paymentGateways = $tenant->status === 'active' && $entitlements->allows((int) $tenant->id, 'vtu_enabled') ? app(\App\Services\Payments\PaymentGatewayRegistry::class)->enabled() : [];
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

    public function transactions(Request $request)
    {
        $tenant = $this->ownerTenant($request);
        $transactions = DB::table('transactions')
            ->where('tenant_id', $tenant->id)
            ->when($request->filled('reference'), fn ($query) => $query->where('reference', 'like', '%'.trim((string) $request->query('reference')).'%'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->query('status')))
            ->orderByDesc('created_at')->paginate(30)->withQueryString();
        return view('tenant.transactions', compact('tenant', 'transactions'));
    }


    public function purchaseForm(Request $request, \App\Services\Subscriptions\SubscriptionEntitlementService $entitlements)
    {
        $tenant = $this->ownerTenant($request);
        abort_unless($entitlements->allows((int) $tenant->id, 'vtu_enabled'), 402, 'Your reseller subscription does not currently include service purchasing.');
        $services = DB::table('service_catalogue')->where('status', 'active')->orderBy('category')->orderBy('name')->get();
        $visible = collect();
        foreach ($services as $service) {
            $mapping = DB::table('provider_services')->join('providers', 'providers.id', '=', 'provider_services.provider_id')
                ->where('provider_services.service_catalogue_id', $service->id)->where('provider_services.status', 'active')
                ->where('providers.status', 'active')->where('providers.environment', config('providers.environment', 'sandbox'))
                ->orderBy('provider_services.priority')->orderBy('provider_services.id')->select('provider_services.id')->first();
            if (!$mapping) continue;
            $prices = DB::table('pricing_rules')->where('service_catalogue_id', $service->id)->where('status', 'active')
                ->where(function ($query) use ($mapping) { $query->where('provider_service_id', $mapping->id)->orWhereNull('provider_service_id'); })
                ->where('effective_from', '<=', now())->where(function ($query) { $query->whereNull('effective_until')->orWhere('effective_until', '>', now()); });
            $price = (clone $prices)->where('tenant_id', $tenant->id)->orderByRaw('CASE WHEN provider_service_id IS NULL THEN 1 ELSE 0 END')->orderByDesc('effective_from')->first()
                ?: (clone $prices)->whereNull('tenant_id')->orderByRaw('CASE WHEN provider_service_id IS NULL THEN 1 ELSE 0 END')->orderByDesc('effective_from')->first();
            if (!$price) continue;
            $service->sell_minor = (int) $price->sell_minor;
            $service->validation_schema = json_decode($service->validation_schema ?: '{}', true) ?: [];
            $service->idempotency_key = (string) Str::uuid();
            $visible->push($service);
        }
        return view('tenant.purchase', compact('tenant', 'visible'));
    }

    public function purchase(Request $request, \App\Services\Subscriptions\SubscriptionEntitlementService $entitlements, VtuTransactionService $transactions): \Illuminate\Http\RedirectResponse
    {
        $tenant = $this->ownerTenant($request);
        abort_unless($entitlements->allows((int) $tenant->id, 'vtu_enabled'), 402, 'Your reseller subscription does not currently include service purchasing.');
        $data = $request->validate([
            'service_code' => ['required', 'string', 'max:64', 'exists:service_catalogue,service_code'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:120'],
            'customer' => ['required', 'array'],
        ]);
        $wallet = DB::table('wallets')->where('owner_type', 'tenant')->where('owner_id', $tenant->id)->where('currency', 'NGN')->where('status', 'active')->first();
        abort_unless($wallet, 409, 'Your reseller wallet is not available.');
        $service = DB::table('service_catalogue')->where('service_code', $data['service_code'])->firstOrFail();
        try {
            $tx = $transactions->reserve((int) $wallet->id, (int) $service->id, $data['idempotency_key'], $data['customer'], (int) $tenant->id);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['purchase' => $exception->getMessage()])->withInput();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        }
        if (($tx['status'] ?? null) === 'reserved') SubmitVtuTransaction::dispatch((int) $tx['id'])->afterCommit();
        return redirect()->route('tenant.transactions')->with('status', 'Purchase accepted. Reference: '.$tx['reference'].'. Track the final status in transaction history.');
    }

}
