<?php

namespace App\Http\Controllers;

use App\Models\KycRecord;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PlatformGovernanceController
{
    public function users(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['super-admin', 'operations-admin']), 403);
        $query = User::query()->with('roles')->orderByDesc('created_at');
        if ($request->filled('q')) $query->where(function ($q) use ($request) { $term = '%'.$request->string('q')->toString().'%'; $q->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term); });
        if ($request->filled('status')) $query->where('status', $request->string('status')->toString());
        $users = $query->paginate(30)->withQueryString();
        return view('platform.users', compact('users'));
    }

    public function updateUserStatus(Request $request, int $user, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['active','suspended'])], 'reason' => ['required','string','min:8','max:500']]);
        $record = User::findOrFail($user);
        abort_if((int) $record->id === (int) $request->user()->id, 409, 'You cannot suspend your own administrator account.');
        abort_if($record->hasAnyRole(['super-admin','operations-admin','finance-admin','kyc-reviewer']), 409, 'Platform staff role changes require a separate privileged access procedure.');
        $before = $record->status;
        $record->update(['status' => $data['status']]);
        $audit->record('user.status_changed', null, User::class, (string) $record->id, $data['reason'], ['before' => $before, 'after' => $record->status]);
        return back()->with('status', 'User status updated.');
    }

    public function apiActivity(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['super-admin','operations-admin']), 403);
        $query = DB::table('api_requests')->leftJoin('api_clients', 'api_clients.id', '=', 'api_requests.api_client_id')->leftJoin('tenants', 'tenants.id', '=', 'api_clients.tenant_id')
            ->select('api_requests.*', 'api_clients.name as client_name', 'tenants.name as tenant_name')->orderByDesc('api_requests.created_at');
        if ($request->filled('status')) $query->where('api_requests.status_code', $request->integer('status'));
        if ($request->filled('path')) $query->where('api_requests.path', 'like', '%'.$request->string('path')->toString().'%');
        $requests = $query->paginate(40)->withQueryString();
        return view('platform.api-activity', compact('requests'));
    }

    public function kyc(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['super-admin', 'kyc-reviewer']), 403);
        $query = KycRecord::query()->with('reviewer')->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'under_review' THEN 1 ELSE 2 END")->latest();
        if ($request->filled('status')) $query->where('status', $request->string('status')->toString());
        $records = $query->paginate(25)->withQueryString();
        return view('platform.kyc', compact('records'));
    }

    public function reviewKyc(Request $request, int $record, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['super-admin', 'kyc-reviewer']), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected', 'needs_information'])],
            'review_notes' => ['required', 'string', 'min:8', 'max:2000'],
        ]);
        $before = null;
        DB::transaction(function () use ($record, $data, $request, &$before): void {
            $kyc = KycRecord::query()->lockForUpdate()->findOrFail($record);
            abort_if(in_array($kyc->status, ['approved', 'rejected'], true), 409, 'This KYC decision is already final. Create a new review record for a later re-verification.');
            $before = $kyc->status;
            $kyc->update(['status' => $data['status'], 'review_notes' => $data['review_notes'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        }, 3);
        $kyc = KycRecord::findOrFail($record);
        $audit->record('kyc.reviewed', $kyc->tenant_id, KycRecord::class, (string) $kyc->id, 'KYC decision updated', ['before' => $before, 'after' => $data['status']]);
        return back()->with('status', 'KYC decision recorded.');
    }

    public function support(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['super-admin', 'operations-admin']), 403);
        $query = DB::table('support_tickets')->leftJoin('users', 'users.id', '=', 'support_tickets.user_id')
            ->select('support_tickets.*', 'users.name as requester_name', 'users.email as requester_email')->orderByRaw("CASE support_tickets.status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END")->orderByDesc('support_tickets.created_at');
        if ($request->filled('status')) $query->where('support_tickets.status', $request->string('status')->toString());
        $tickets = $query->paginate(30)->withQueryString();
        return view('platform.support', compact('tickets'));
    }

    public function updateSupport(Request $request, int $ticket, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['super-admin', 'operations-admin']), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'in_progress', 'resolved', 'closed'])], 'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])], 'reason' => ['required', 'string', 'min:8', 'max:500']]);
        $record = DB::table('support_tickets')->where('id', $ticket)->first();
        abort_unless($record, 404);
        DB::table('support_tickets')->where('id', $ticket)->update(['status' => $data['status'], 'priority' => $data['priority'], 'updated_at' => now()]);
        $audit->record('support.ticket_updated', $record->tenant_id ? (int) $record->tenant_id : null, 'support_tickets', (string) $ticket, $data['reason'], ['status' => $data['status'], 'priority' => $data['priority']]);
        return back()->with('status', 'Support ticket updated.');
    }

    public function subscriptions(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['super-admin', 'operations-admin']), 403);
        $plans = DB::table('plans')->orderBy('price_minor')->get();
        $tenants = DB::table('tenants')->whereIn('status', ['active', 'pending'])->orderBy('name')->get();
        $subscriptions = DB::table('subscriptions')->join('tenants', 'tenants.id', '=', 'subscriptions.tenant_id')->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->select('subscriptions.*', 'tenants.name as tenant_name', 'plans.name as plan_name', 'plans.price_minor')->orderByDesc('subscriptions.created_at')->paginate(30);
        return view('platform.subscriptions', compact('plans', 'tenants', 'subscriptions'));
    }

    public function createPlan(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $data = $request->validate(['code' => ['required', 'alpha_dash:ascii', 'max:64', 'unique:plans,code'], 'name' => ['required', 'string', 'max:100'], 'price_minor' => ['required', 'integer', 'min:0'], 'billing_interval' => ['required', Rule::in(['month', 'year'])], 'entitlements_json' => ['nullable', 'json']]);
        $entitlementsObject = isset($data['entitlements_json']) && trim($data['entitlements_json']) !== '' ? json_decode($data['entitlements_json']) : (object) [];
        abort_unless(is_object($entitlementsObject), 422, 'Entitlements must be a JSON object.');
        $entitlements = (array) $entitlementsObject;
        $id = DB::table('plans')->insertGetId(['code' => strtolower($data['code']), 'name' => $data['name'], 'price_minor' => $data['price_minor'], 'currency' => 'NGN', 'billing_interval' => $data['billing_interval'], 'entitlements' => json_encode($entitlements), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $audit->record('subscription.plan_created', null, 'plans', (string) $id, 'Plan created in platform control panel');
        return back()->with('status', 'Subscription plan created.');
    }

    public function assignSubscription(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $data = $request->validate(['tenant_id' => ['required', Rule::exists('tenants', 'id')->where('status', 'active')], 'plan_id' => ['required', Rule::exists('plans', 'id')->where('status', 'active')], 'starts_at' => ['required', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'], 'reason' => ['required', 'string', 'min:8', 'max:500']]);
        $plan = DB::table('plans')->where('id', $data['plan_id'])->where('status', 'active')->first();
        abort_unless($plan, 422, 'The selected plan is not active.');
        $isFree = (int) $plan->price_minor === 0;
        $subscriptionStatus = $isFree ? 'active' : 'pending_payment';
        $id = DB::transaction(function () use ($data, $isFree, $subscriptionStatus): int {
            $tenantId = (int) $data['tenant_id'];
            DB::table('tenants')->where('id', $tenantId)->lockForUpdate()->first();
            if ($isFree) DB::table('subscriptions')->where('tenant_id', $tenantId)->whereIn('status', ['active','trial','grace'])->update(['status' => 'superseded', 'ends_at' => now(), 'updated_at' => now()]);
            return DB::table('subscriptions')->insertGetId(['tenant_id' => $tenantId, 'plan_id' => $data['plan_id'], 'status' => $subscriptionStatus, 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'] ?? null, 'grace_ends_at' => null, 'created_at' => now(), 'updated_at' => now()]);
        }, 3);
        $audit->record('subscription.assigned', (int) $data['tenant_id'], 'subscriptions', (string) $id, $data['reason'], ['plan_id' => $data['plan_id'], 'status' => $subscriptionStatus]);
        return back()->with('status', $isFree ? 'Free subscription activated.' : 'Paid subscription created as pending payment. It will not grant paid entitlements until a verified billing integration activates it.');
    }

    public function reports(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['super-admin', 'finance-admin']), 403);
        $summary = [
            'transactions' => DB::table('transactions')->count(),
            'successful' => DB::table('transactions')->where('status', 'successful')->count(),
            'failed' => DB::table('transactions')->where('status', 'failed')->count(),
            'pending' => DB::table('transactions')->whereIn('status', ['reserved', 'submitted', 'pending'])->count(),
            'deposits' => DB::table('deposits')->count(),
            'journals' => DB::table('ledger_journals')->where('status', 'posted')->count(),
            'gross_margin_minor' => (int) (DB::table('transactions')->where('status', 'successful')->selectRaw('COALESCE(SUM(sell_minor - COALESCE(cost_minor, 0)), 0) as margin')->first()->margin ?? 0),
            'open_risk_events' => DB::table('risk_events')->where('status', 'open')->count(),
            'api_requests_30d' => DB::table('api_requests')->where('created_at', '>=', now()->subDays(30))->count(),
        ];
        return view('platform.reports', compact('summary'));
    }

    public function exportTransactions(Request $request): StreamedResponse
    {
        abort_unless($request->user()->hasAnyRole(['super-admin', 'finance-admin']), 403);
        $from = $request->date('from'); $to = $request->date('to');
        $query = DB::table('transactions')->orderBy('id');
        if ($from) $query->where('created_at', '>=', $from->startOfDay());
        if ($to) $query->where('created_at', '<=', $to->endOfDay());
        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['reference', 'tenant_id', 'wallet_id', 'service_id', 'status', 'amount_minor', 'cost_minor', 'currency', 'provider_reference', 'created_at', 'completed_at']);
            $query->chunk(500, function ($rows) use ($out): void { foreach ($rows as $row) fputcsv($out, [$row->reference, $row->tenant_id, $row->wallet_id, $row->service_catalogue_id, $row->status, $row->sell_minor, $row->cost_minor, $row->currency, $row->provider_reference, $row->created_at, $row->completed_at]); });
            fclose($out);
        }, 'vtu-transactions-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function security(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['super-admin', 'operations-admin']), 403);
        $audits = DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.actor_user_id')
            ->select('audit_logs.*', 'users.name as actor_name', 'users.email as actor_email')->latest('audit_logs.created_at')->paginate(30, ['*'], 'audit_page');
        $risks = DB::table('risk_events')->leftJoin('users', 'users.id', '=', 'risk_events.user_id')
            ->select('risk_events.*', 'users.name as user_name', 'users.email as user_email')->orderByRaw("CASE risk_events.status WHEN 'open' THEN 0 ELSE 1 END")->latest('risk_events.created_at')->paginate(20, ['*'], 'risk_page');
        return view('platform.security', compact('audits', 'risks'));
    }

    public function resolveRisk(Request $request, int $risk, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['super-admin', 'operations-admin']), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['resolved', 'dismissed'])], 'resolution_note' => ['required', 'string', 'min:8', 'max:2000']]);
        $record = DB::table('risk_events')->where('id', $risk)->first();
        abort_unless($record, 404);
        abort_if($record->status !== 'open', 409, 'This risk event has already been reviewed.');
        $updated = DB::table('risk_events')->where('id', $risk)->where('status', 'open')->update(['status' => $data['status'], 'resolution_note' => $data['resolution_note'], 'reviewed_by' => $request->user()->id, 'updated_at' => now()]);
        abort_if($updated !== 1, 409, 'This risk event has already been reviewed.');
        $audit->record('risk_event.reviewed', $record->tenant_id ? (int) $record->tenant_id : null, 'risk_events', (string) $risk, $data['resolution_note'], ['status' => $data['status']]);
        return back()->with('status', 'Risk event review saved.');
    }

    public function mySupport(Request $request)
    {
        $tickets = DB::table('support_tickets')->where('user_id', $request->user()->id)->latest()->paginate(20);
        return view('support.index', compact('tickets'));
    }

    public function createSupport(Request $request): RedirectResponse
    {
        $data = $request->validate(['subject' => ['required', 'string', 'max:180'], 'priority' => ['required', Rule::in(['low', 'normal', 'high'])], 'description' => ['required', 'string', 'min:20', 'max:5000']]);
        $tenant = $request->attributes->get('tenant');
        $ref = 'SUP-'.strtoupper(bin2hex(random_bytes(6)));
        DB::table('support_tickets')->insert(['tenant_id' => $tenant?->id, 'user_id' => $request->user()->id, 'reference' => $ref, 'subject' => $data['subject'], 'status' => 'open', 'priority' => $data['priority'], 'description' => $data['description'], 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('status', 'Your support request has been submitted. Reference: '.$ref);
    }
}
