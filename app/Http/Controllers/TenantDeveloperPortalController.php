<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\Developers\ApiKeyService;
use App\Services\Webhooks\WebhookUrlPolicy;
use App\Services\Subscriptions\SubscriptionEntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class TenantDeveloperPortalController
{
    private function tenantOwner(Request $request): object
    {
        $tenant = $request->attributes->get('tenant');
        abort_unless($tenant && $tenant->status === 'active', 404);
        abort_unless($request->user()->tenants()->whereKey($tenant->id)->wherePivot('status', 'active')->wherePivot('role', 'tenant-owner')->exists(), 403);
        return $tenant;
    }

    public function index(Request $request, SubscriptionEntitlementService $entitlements)
    {
        $tenant = $this->tenantOwner($request);
        $apiEnabled = $entitlements->allows((int) $tenant->id, 'api_enabled');
        $clients = DB::table('api_clients')->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->orderByDesc('id')->get();
        foreach ($clients as $client) {
            $client->keys = DB::table('api_keys')->where('api_client_id', $client->id)->select(['id','name','prefix','scopes','last_used_at','expires_at','revoked_at'])->orderByDesc('id')->get();
        }
        $webhooks = DB::table('webhook_endpoints')->where('tenant_id', $tenant->id)->select(['id','url','events','status','created_at'])->latest()->get();
        $deliveries = DB::table('webhook_deliveries')->join('webhook_endpoints', 'webhook_endpoints.id', '=', 'webhook_deliveries.webhook_endpoint_id')->where('webhook_endpoints.tenant_id', $tenant->id)->select('webhook_deliveries.*')->latest('webhook_deliveries.created_at')->limit(30)->get();
        return view('tenant.developers', compact('tenant', 'clients', 'webhooks', 'deliveries', 'apiEnabled'));
    }

    public function createClient(Request $request, AuditLogger $audit): RedirectResponse
    {
        $tenant = $this->tenantOwner($request);
        abort_unless(app(SubscriptionEntitlementService::class)->allows((int) $tenant->id, 'api_enabled'), 402, 'The tenant subscription does not include API access or is not active.');
        $data = $request->validate(['name' => ['required','string','max:120'], 'ip_allowlist' => ['nullable','array'], 'ip_allowlist.*' => ['ip']]);
        $id = DB::table('api_clients')->insertGetId(['tenant_id' => $tenant->id, 'user_id' => $request->user()->id, 'name' => $data['name'], 'environment' => 'sandbox', 'scopes' => json_encode(['transactions:read','transactions:write','balance:read']), 'ip_allowlist' => isset($data['ip_allowlist']) ? json_encode(array_values(array_unique($data['ip_allowlist']))) : null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $audit->record('api_client.created', (int) $tenant->id, 'api_clients', (string) $id, 'API client created from tenant developer portal');
        return back()->with('status', 'Sandbox API client created. Generate a key and store the secret securely.');
    }

    public function createKey(Request $request, int $client, ApiKeyService $keys, AuditLogger $audit): RedirectResponse
    {
        $tenant = $this->tenantOwner($request);
        abort_unless(app(SubscriptionEntitlementService::class)->allows((int) $tenant->id, 'api_enabled'), 402, 'The tenant subscription does not include API access or is not active.');
        $data = $request->validate(['name' => ['required','string','max:100'], 'scopes' => ['required','array','min:1'], 'scopes.*' => [Rule::in(['transactions:read','transactions:write','balance:read'])]]);
        $apiClient = DB::table('api_clients')->where('id', $client)->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($apiClient, 404);
        $key = $keys->create((int) $client, $data['name'], $data['scopes']);
        $audit->record('api_key.created', (int) $tenant->id, 'api_keys', (string) $key['id'], 'API key created; secret displayed once', ['client_id' => $client, 'scopes' => $data['scopes']]);
        return back()->with('new_api_secret', $key['secret'])->with('status', 'API key created. Copy the secret now; it will not be shown again.');
    }

    public function revokeKey(Request $request, int $client, int $key, ApiKeyService $keys, AuditLogger $audit): RedirectResponse
    {
        $tenant = $this->tenantOwner($request);
        abort_unless(DB::table('api_clients')->where('id', $client)->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->exists(), 404);
        $keys->revoke((int) $client, (int) $key);
        $audit->record('api_key.revoked', (int) $tenant->id, 'api_keys', (string) $key, 'Tenant owner revoked API key');
        return back()->with('status', 'API key revoked. Requests using it will be rejected.');
    }

    public function createWebhook(Request $request, WebhookUrlPolicy $urlPolicy, AuditLogger $audit): RedirectResponse
    {
        $tenant = $this->tenantOwner($request);
        abort_unless(app(SubscriptionEntitlementService::class)->allows((int) $tenant->id, 'api_enabled'), 402, 'The tenant subscription does not include API access or is not active.');
        $data = $request->validate(['url' => ['required','url','starts_with:https://','max:2000'], 'events' => ['required','array','min:1'], 'events.*' => ['required','string','distinct',Rule::in(['transaction.pending','transaction.successful','transaction.failed','wallet.deposit.successful','wallet.credit.low','subscription.status_changed'])]]);
        try { $urlPolicy->assertPublicHttps($data['url']); } catch (InvalidArgumentException $exception) { return back()->withErrors(['url' => $exception->getMessage()])->withInput(); }
        $secret = Str::random(64);
        $id = DB::table('webhook_endpoints')->insertGetId(['tenant_id' => $tenant->id, 'api_client_id' => null, 'url' => $data['url'], 'secret_encrypted' => encrypt($secret), 'events' => json_encode($data['events']), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $audit->record('webhook.created', (int) $tenant->id, 'webhook_endpoints', (string) $id, 'Webhook endpoint created; signing secret displayed once');
        return back()->with('new_webhook_secret', $secret)->with('status', 'Webhook endpoint created. Copy the signing secret now; it will not be shown again.');
    }
    public function setWebhookStatus(Request $request, int $endpoint, AuditLogger $audit): RedirectResponse
    {
        $tenant = $this->tenantOwner($request);
        $data = $request->validate(['status' => ['required', Rule::in(['active','inactive'])], 'reason' => ['required','string','min:8','max:500']]);
        $record = DB::table('webhook_endpoints')->where('id', $endpoint)->where('tenant_id', $tenant->id)->first();
        abort_unless($record, 404);
        if ($data['status'] === 'active') {
            abort_unless(app(SubscriptionEntitlementService::class)->allows((int) $tenant->id, 'api_enabled'), 402, 'The tenant subscription does not include API access or is not active.');
            try { app(WebhookUrlPolicy::class)->assertPublicHttps($record->url); } catch (InvalidArgumentException $exception) { return back()->withErrors(['webhook' => $exception->getMessage()]); }
        }
        DB::table('webhook_endpoints')->where('id', $endpoint)->where('tenant_id', $tenant->id)->update(['status' => $data['status'], 'updated_at' => now()]);
        $audit->record('webhook.status_changed', (int) $tenant->id, 'webhook_endpoints', (string) $endpoint, $data['reason'], ['before' => $record->status, 'after' => $data['status']]);
        return back()->with('status', 'Webhook endpoint status updated.');
    }
}
