<?php
namespace App\Http\Controllers;
use App\Services\AuditLogger;
use App\Services\Webhooks\WebhookUrlPolicy;
use App\Services\Subscriptions\SubscriptionEntitlementService;
use InvalidArgumentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
final class DeveloperWebhookController
{
    public function store(Request $request, int $tenant, AuditLogger $audit, WebhookUrlPolicy $urlPolicy, SubscriptionEntitlementService $entitlements): JsonResponse
    {
        $current = $request->attributes->get('tenant');
        abort_unless($current && (int) $current->id === $tenant, 404);
        abort_unless($request->user()->tenants()->whereKey($tenant)->wherePivot('status', 'active')->wherePivot('role', 'tenant-owner')->exists(), 403);
        abort_unless($entitlements->allows((int) $tenant, 'api_enabled'), 402, 'The tenant subscription does not include API access or is not active.');
        $data = $request->validate(['url' => ['required', 'url', 'starts_with:https://', 'max:2000'], 'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string', 'distinct', 'in:transaction.pending,transaction.successful,transaction.failed,wallet.deposit.successful,wallet.credit.low,subscription.status_changed']]);
        try { $urlPolicy->assertPublicHttps($data['url']); } catch (InvalidArgumentException $exception) { return response()->json(['message' => $exception->getMessage()], 422); }
        $secret = Str::random(64);
        $id = DB::table('webhook_endpoints')->insertGetId(['tenant_id' => $tenant, 'api_client_id' => null, 'url' => $data['url'],
            'secret_encrypted' => encrypt($secret), 'events' => json_encode($data['events']), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $audit->record('webhook.created', $tenant, 'webhook_endpoints', (string) $id, 'Tenant webhook endpoint created');
        return response()->json(['data' => ['id' => $id, 'secret' => $secret]], 201);
    }
    public function index(Request $request, int $tenant): JsonResponse
    {
        $current = $request->attributes->get('tenant');
        abort_unless($current && (int) $current->id === $tenant, 404);
        abort_unless($request->user()->tenants()->whereKey($tenant)->wherePivot('status', 'active')->wherePivot('role', 'tenant-owner')->exists(), 403);
        return response()->json(['data' => DB::table('webhook_endpoints')->where('tenant_id', $tenant)->select(['id','url','events','status','created_at'])->latest()->paginate(20)]);
    }
    public function setStatus(Request $request, int $tenant, int $endpoint, AuditLogger $audit, WebhookUrlPolicy $urlPolicy, SubscriptionEntitlementService $entitlements): JsonResponse
    {
        $current = $request->attributes->get('tenant');
        abort_unless($current && (int) $current->id === $tenant, 404);
        abort_unless($request->user()->tenants()->whereKey($tenant)->wherePivot('status', 'active')->wherePivot('role', 'tenant-owner')->exists(), 403);
        $data = $request->validate(['status' => ['required', 'in:active,inactive'], 'reason' => ['required', 'string', 'min:8', 'max:500']]);
        $record = DB::table('webhook_endpoints')->where('id', $endpoint)->where('tenant_id', $tenant)->first();
        abort_unless($record, 404);
        if ($data['status'] === 'active') {
            abort_unless($entitlements->allows((int) $tenant, 'api_enabled'), 402, 'The tenant subscription does not include API access or is not active.');
            try { $urlPolicy->assertPublicHttps($record->url); } catch (InvalidArgumentException $exception) { return response()->json(['message' => $exception->getMessage()], 422); }
        }
        DB::table('webhook_endpoints')->where('id', $endpoint)->where('tenant_id', $tenant)->update(['status' => $data['status'], 'updated_at' => now()]);
        $audit->record('webhook.status_changed', $tenant, 'webhook_endpoints', (string) $endpoint, $data['reason'], ['before' => $record->status, 'after' => $data['status']]);
        return response()->json(['message' => 'Webhook endpoint status updated.']);
    }
}
