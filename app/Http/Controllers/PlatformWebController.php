<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Jobs\RequeryVtuTransaction;
use App\Services\Accounting\WalletProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\AuditLogger;

final class PlatformWebController
{
    public function dashboard(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['super-admin', 'operations-admin', 'finance-admin', 'kyc-reviewer']), 403);
        $metrics = [
            'users' => DB::table('users')->count(), 'tenants' => DB::table('tenants')->count(),
            'active_tenants' => DB::table('tenants')->where('status', 'active')->count(),
            'transactions' => DB::table('transactions')->count(),
            'pending_transactions' => DB::table('transactions')->whereIn('status', ['reserved', 'submitted', 'pending'])->count(),
            'pending_deposits' => DB::table('deposits')->whereIn('status', ['initiated', 'pending', 'under_review'])->count(),
        ];
        return view('platform.dashboard', compact('metrics'));
    }

    public function transactions(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['super-admin', 'operations-admin', 'finance-admin']), 403);
        $query = DB::table('transactions')->leftJoin('tenants','tenants.id','=','transactions.tenant_id')
            ->select('transactions.*','tenants.name as tenant_name')->orderByDesc('transactions.created_at');
        if ($request->filled('status')) $query->where('transactions.status', $request->string('status')->toString());
        if ($request->filled('reference')) $query->where('transactions.reference', 'like', '%'.$request->string('reference')->toString().'%');
        $transactions = $query->paginate(30)->withQueryString();
        return view('platform.transactions', compact('transactions'));
    }

    public function requeryTransaction(Request $request, int $transaction, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['super-admin','operations-admin']), 403);
        $data = $request->validate(['reason'=>['required','string','min:8','max:500']]);
        $tx = DB::table('transactions')->where('id',$transaction)->first();
        abort_unless($tx && in_array($tx->status,['submitted','pending'],true), 409, 'Only submitted or pending transactions can be requeried.');
        abort_unless($tx->provider_reference, 409, 'No supplier reference is available. This transaction requires manual supplier reconciliation; it will not be resubmitted.');
        RequeryVtuTransaction::dispatch((int)$tx->id);
        $audit->record('transaction.requery_requested',$tx->tenant_id ? (int)$tx->tenant_id : null,'transactions',(string)$tx->id,$data['reason'],['reference'=>$tx->reference]);
        return back()->with('status','A safe provider status requery has been queued. The transaction will not be resubmitted.');
    }

    public function tenants(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['super-admin', 'operations-admin']), 403);
        $tenants = Tenant::with('domains')->latest()->paginate(25);
        return view('platform.tenants', compact('tenants'));
    }

    public function createTenant(Request $request, AuditLogger $audit, WalletProvisioningService $wallets): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'alpha_dash:ascii', 'min:3', 'max:50', 'unique:tenants,slug', Rule::notIn(config('tenancy.reserved_slugs'))],
            'owner_email' => ['nullable', 'email', 'exists:users,email'],
        ]);
        DB::transaction(function () use ($data, $wallets, $audit): void {
            $tenant = Tenant::create(['name' => $data['name'], 'slug' => strtolower($data['slug']), 'status' => 'pending', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN']);
            $wallets->create('tenant', (int) $tenant->id, (int) $tenant->id);
            if (! empty($data['owner_email'])) {
                $owner = \App\Models\User::where('email', $data['owner_email'])->firstOrFail();
                abort_unless($owner->status === 'active', 422, 'Tenant owner account must be active.');
                $owner->assignRole('tenant-owner');
                $tenant->users()->syncWithoutDetaching([$owner->id => ['role' => 'tenant-owner', 'status' => 'active']]);
            }
            $base = strtolower((string) config('tenancy.base_domain'));
            if ($base && $base !== 'localhost') TenantDomain::create(['tenant_id' => $tenant->id, 'hostname' => $tenant->slug.'.'.$base, 'is_primary' => true, 'tls_status' => 'pending']);
            $audit->record('tenant.created', (int) $tenant->id, Tenant::class, (string) $tenant->id, 'Created in platform admin dashboard', ['slug' => $tenant->slug]);
        }, 3);
        return redirect()->route('platform.tenants')->with('status', 'Tenant created in pending status.');
    }

    public function updateTenantStatus(Request $request, int $tenant, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended', 'pending'])], 'reason' => ['required', 'string', 'min:8', 'max:500']]);
        $record = Tenant::findOrFail($tenant); $before = $record->status;
        $record->update(['status' => $data['status']]);
        $audit->record('tenant.status_changed', (int) $record->id, Tenant::class, (string) $record->id, $data['reason'], ['before' => $before, 'after' => $record->status]);
        return redirect()->route('platform.tenants')->with('status', 'Tenant status updated.');
    }
}
