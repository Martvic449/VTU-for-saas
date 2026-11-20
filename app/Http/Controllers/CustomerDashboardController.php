<?php

namespace App\Http\Controllers;

use App\Jobs\SubmitVtuTransaction;
use App\Services\Accounting\WalletBalanceService;
use App\Services\Transactions\VtuTransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class CustomerDashboardController
{
    public function __invoke(Request $request, WalletBalanceService $balances)
    {
        $user = $request->user();
        if ($user->hasAnyRole(['super-admin', 'operations-admin', 'finance-admin', 'kyc-reviewer'])) {
            return redirect()->route('platform.dashboard');
        }
        $wallet = DB::table('wallets')->where('owner_type', 'user')->where('owner_id', $user->id)->where('currency', 'NGN')->first();
        $balance = $wallet ? $balances->availableMinor((int) $wallet->id) : 0;
        $paymentGateways = app(\App\Services\Payments\PaymentGatewayRegistry::class)->enabled();
        $tenantApplications = DB::table('tenant_user')->join('tenants', 'tenants.id', '=', 'tenant_user.tenant_id')->leftJoin('tenant_domains', function ($join) { $join->on('tenant_domains.tenant_id', '=', 'tenants.id')->where('tenant_domains.is_primary', true); })->where('tenant_user.user_id', $user->id)->select('tenants.id','tenants.name','tenants.slug','tenants.status','tenant_domains.hostname','tenant_domains.verified_at','tenant_domains.tls_status')->get();
        $services = DB::table('service_catalogue')->where('status', 'active')->orderBy('category')->orderBy('name')->get();
        $visibleServices = collect();
        foreach ($services as $service) {
            $mapping = DB::table('provider_services')->join('providers', 'providers.id', '=', 'provider_services.provider_id')
                ->where('provider_services.service_catalogue_id', $service->id)->where('provider_services.status', 'active')
                ->where('providers.status', 'active')->where('providers.environment', config('providers.environment', 'sandbox'))
                ->orderBy('provider_services.priority')->orderBy('provider_services.id')->select('provider_services.id')->first();
            if (! $mapping) continue;
            $prices = DB::table('pricing_rules')->where('service_catalogue_id', $service->id)->whereNull('tenant_id')->where('status', 'active')
                ->where(function ($q) use ($mapping) { $q->where('provider_service_id', $mapping->id)->orWhereNull('provider_service_id'); })
                ->where('effective_from', '<=', now())->where(function ($q) { $q->whereNull('effective_until')->orWhere('effective_until', '>', now()); })
                ->orderByRaw('CASE WHEN provider_service_id IS NULL THEN 1 ELSE 0 END')->orderByDesc('effective_from')->first();
            if (! $prices) continue;
            $service->sell_minor = (int) $prices->sell_minor;
            $service->validation_schema = json_decode($service->validation_schema ?: '{}', true) ?: [];
            $visibleServices->push($service);
        }
        $services = $visibleServices;
        $purchaseKeys = [];
        foreach ($services as $service) {
            $service->validation_schema = json_decode($service->validation_schema ?: '{}', true) ?: [];
            $purchaseKeys[$service->service_code] = (string) Str::uuid();
        }
        return view('tenant.customer-dashboard', compact('wallet', 'balance', 'transactions', 'deposits', 'paymentGateways', 'services', 'purchaseKeys', 'tenantApplications'));
    }

    public function activity(Request $request)
    {
        $user = $request->user();
        abort_if($user->hasAnyRole(['super-admin', 'operations-admin', 'finance-admin', 'kyc-reviewer']), 404);
        $wallet = DB::table('wallets')->where('owner_type', 'user')->where('owner_id', $user->id)->where('currency', 'NGN')->first();
        $transactions = $wallet ? DB::table('transactions')->where('wallet_id', $wallet->id)->latest()->paginate(20, ['*'], 'transactions_page') : collect();
        $deposits = $wallet ? DB::table('deposits')->where('wallet_id', $wallet->id)->latest()->paginate(20, ['*'], 'deposits_page') : collect();

        return view('tenant.customer-activity', compact('transactions', 'deposits'));
    }

    public function purchase(Request $request, VtuTransactionService $transactions): RedirectResponse
    {
        $user = $request->user();
        $wallet = DB::table('wallets')->where('owner_type', 'user')->where('owner_id', $user->id)->where('currency', 'NGN')->where('status', 'active')->first();
        abort_unless($wallet, 409, 'Your service wallet is not available.');
        $data = $request->validate(['service_code' => ['required', 'string', 'max:64', 'exists:service_catalogue,service_code'], 'idempotency_key' => ['required', 'string', 'min:16', 'max:120'], 'customer' => ['required', 'array']]);
        $service = DB::table('service_catalogue')->where('service_code', $data['service_code'])->firstOrFail();
        try {
            $tx = $transactions->reserve((int) $wallet->id, (int) $service->id, $data['idempotency_key'], $data['customer'], null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['purchase' => $exception->getMessage()])->withInput();
        }
        if (($tx['status'] ?? null) === 'reserved') SubmitVtuTransaction::dispatch((int) $tx['id'])->afterCommit();
        return redirect()->route('dashboard')->with('status', 'Purchase accepted. Reference: '.$tx['reference'].'. Track the final result in recent transactions.');
    }
}
