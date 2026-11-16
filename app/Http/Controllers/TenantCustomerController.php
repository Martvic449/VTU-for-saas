<?php
namespace App\Http\Controllers;

use App\Jobs\SubmitVtuTransaction;
use App\Models\User;
use App\Services\Accounting\WalletBalanceService;
use App\Services\Accounting\WalletProvisioningService;
use App\Services\Transactions\VtuTransactionService;
use App\Services\Subscriptions\SubscriptionEntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class TenantCustomerController
{
    public function registerForm(Request $request)
    {
        abort_unless($request->attributes->get('tenant'), 404);
        return view('tenant.customer-register', ['tenant' => $request->attributes->get('tenant')]);
    }

    public function register(Request $request, WalletProvisioningService $wallets): RedirectResponse
    {
        $tenant = $request->attributes->get('tenant'); abort_unless($tenant && $tenant->status === 'active', 404);
        $data = $request->validate(['name' => ['required','string','max:160'], 'email' => ['required','email','max:190','unique:users,email'], 'password' => ['required','string','min:12','confirmed']]);
        $user = DB::transaction(function () use ($data, $tenant, $wallets): User {
            $user = User::create($data + ['status' => 'active']);
            $user->assignRole('customer');
            $customerId = DB::table('tenant_customers')->insertGetId(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $wallets->create('tenant_customer', (int) $customerId, (int) $tenant->id);
            return $user;
        }, 3);
        Auth::login($user); $request->session()->regenerate(); $user->sendEmailVerificationNotification();
        return redirect()->route('verification.notice')->with('status', 'Verify your email to activate your customer account with '.$tenant->name.'.');
    }

    public function join(Request $request, WalletProvisioningService $wallets): RedirectResponse
    {
        $tenant = $request->attributes->get('tenant'); abort_unless($tenant && $tenant->status === 'active', 404);
        abort_unless($request->user()->hasVerifiedEmail(), 403, 'Verify your email before joining a reseller storefront.');
        DB::transaction(function () use ($tenant, $request, $wallets): void {
            $customerId = DB::table('tenant_customers')->insertOrIgnore(['tenant_id' => $tenant->id, 'user_id' => $request->user()->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $record = DB::table('tenant_customers')->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->first();
            if ($record) $wallets->create('tenant_customer', (int) $record->id, (int) $tenant->id);
        }, 3);
        return redirect()->route('tenant.customer.dashboard')->with('status', 'Your customer account with '.$tenant->name.' is ready.');
    }

    public function dashboard(Request $request, WalletBalanceService $balances, SubscriptionEntitlementService $entitlements)
    {
        $tenant = $request->attributes->get('tenant'); abort_unless($tenant, 404);
        abort_unless($request->user()->hasVerifiedEmail(), 403, 'Verify your email before purchasing.');
        $customer = DB::table('tenant_customers')->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($customer, 403, 'You do not have an active customer account with this reseller.');
        $wallet = DB::table('wallets')->where('owner_type', 'tenant_customer')->where('owner_id', $customer->id)->where('tenant_id', $tenant->id)->where('status', 'active')->first();
        abort_unless($wallet, 409, 'Your reseller wallet is not available.');
        $balance = $balances->availableMinor((int) $wallet->id);
        $transactions = DB::table('transactions')->where('wallet_id', $wallet->id)->latest()->limit(15)->get();
        $deposits = DB::table('deposits')->where('wallet_id', $wallet->id)->latest()->limit(10)->get();
        $paymentGateways = $tenant->status === 'active' && $entitlements->allows((int) $tenant->id, 'vtu_enabled') ? app(\App\Services\Payments\PaymentGatewayRegistry::class)->enabled() : [];
        $services = $tenant->status === 'active' && $entitlements->allows((int) $tenant->id, 'vtu_enabled') ? DB::table('service_catalogue')->where('status', 'active')->orderBy('category')->orderBy('name')->get() : collect();
        $visible = collect(); $purchaseKeys = [];
        foreach ($services as $service) {
            $mapping = DB::table('provider_services')->join('providers', 'providers.id', '=', 'provider_services.provider_id')
                ->where('provider_services.service_catalogue_id', $service->id)->where('provider_services.status', 'active')
                ->where('providers.status', 'active')->where('providers.environment', config('providers.environment', 'sandbox'))
                ->orderBy('provider_services.priority')->orderBy('provider_services.id')->select('provider_services.id')->first();
            if (! $mapping) continue;
            $active = DB::table('pricing_rules')->where('service_catalogue_id', $service->id)->where('status', 'active')
                ->where(function ($q) use ($mapping) { $q->where('provider_service_id', $mapping->id)->orWhereNull('provider_service_id'); });
            $base = (clone $active)->whereNull('tenant_id')->where('effective_from', '<=', now())->where(function ($q) { $q->whereNull('effective_until')->orWhere('effective_until', '>', now()); })->orderByRaw('CASE WHEN provider_service_id IS NULL THEN 1 ELSE 0 END')->orderByDesc('effective_from')->first();
            $tenantPrice = (clone $active)->where('tenant_id', $tenant->id)->where('effective_from', '<=', now())->where(function ($q) { $q->whereNull('effective_until')->orWhere('effective_until', '>', now()); })->orderByRaw('CASE WHEN provider_service_id IS NULL THEN 1 ELSE 0 END')->orderByDesc('effective_from')->first();
            $price = $tenantPrice ?: $base;
            if (! $price) continue;
            $service->sell_minor = (int) $price->sell_minor;
            $service->validation_schema = json_decode($service->validation_schema ?: '{}', true) ?: [];
            $visible->push($service); $purchaseKeys[$service->service_code] = (string) Str::uuid();
        }
        return view('tenant.reseller-customer-dashboard', compact('tenant','customer','wallet','balance','transactions','deposits','paymentGateways','services','visible','purchaseKeys'));
    }

    public function purchase(Request $request, VtuTransactionService $transactions, SubscriptionEntitlementService $entitlements): RedirectResponse
    {
        $tenant = $request->attributes->get('tenant'); abort_unless($tenant, 404);
        abort_unless($tenant->status === 'active' && $entitlements->allows((int) $tenant->id, 'vtu_enabled'), 402, 'This reseller plan does not currently permit customer purchases.');
        $customer = DB::table('tenant_customers')->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($customer, 403);
        $wallet = DB::table('wallets')->where('owner_type', 'tenant_customer')->where('owner_id', $customer->id)->where('tenant_id', $tenant->id)->where('status', 'active')->first();
        abort_unless($wallet, 409);
        $data = $request->validate(['service_code' => ['required','string','max:64','exists:service_catalogue,service_code'], 'idempotency_key' => ['required','string','min:16','max:120'], 'customer' => ['required','array']]);
        $service = DB::table('service_catalogue')->where('service_code', $data['service_code'])->firstOrFail();
        try { $tx = $transactions->reserve((int) $wallet->id, (int) $service->id, $data['idempotency_key'], $data['customer'], (int) $tenant->id); }
        catch (RuntimeException $exception) { return back()->withErrors(['purchase' => $exception->getMessage()])->withInput(); }
        if (($tx['status'] ?? null) === 'reserved') SubmitVtuTransaction::dispatch((int) $tx['id'])->afterCommit();
        return redirect()->route('tenant.customer.dashboard')->with('status', 'Purchase accepted. Reference: '.$tx['reference'].'. Check its status in your transaction history.');
    }
}
