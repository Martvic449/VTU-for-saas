<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Services\AuditLogger;
use App\Services\Webhooks\WebhookUrlPolicy;
use InvalidArgumentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class PlatformOperationsController
{
    public function providers(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['super-admin', 'operations-admin']), 403);
        return response()->json(['data' => Provider::query()->with('services')->paginate(25)]);
    }

    public function storeProvider(Request $request, AuditLogger $audit, WebhookUrlPolicy $urlPolicy): JsonResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $data = $request->validate([
            'code' => ['required', 'alpha_dash:ascii', 'max:64', 'unique:providers,code'],
            'name' => ['required', 'string', 'max:120'],
            'adapter' => ['required', Rule::in(['configured_http'])],
            'environment' => ['required', Rule::in(['sandbox', 'live'])],
            'status' => ['required', Rule::in(['inactive'])],
            'credentials.api_token' => ['required', 'string', 'min:12', 'max:2000'],
            'settings.purchase_url' => ['required', 'url', 'starts_with:https://'],
            'settings.requery_url' => ['required', 'url', 'starts_with:https://'],
            'settings.response_status_path' => ['nullable', 'string', 'max:100'],
            'settings.response_reference_path' => ['nullable', 'string', 'max:100'],
            'settings.response_message_path' => ['nullable', 'string', 'max:100'],
            'settings.response_cost_path' => ['nullable', 'string', 'max:100'],
            'settings.success_statuses' => ['nullable', 'array'],
            'settings.success_statuses.*' => ['string', 'max:40'],
            'settings.failed_statuses' => ['nullable', 'array'],
            'settings.failed_statuses.*' => ['string', 'max:40'],
        ]);
        try { $urlPolicy->assertPublicHttps($data['settings']['purchase_url']); $urlPolicy->assertPublicHttps($data['settings']['requery_url']); }
        catch (InvalidArgumentException $exception) { return response()->json(['message' => $exception->getMessage()], 422); }
        $provider = Provider::create([
            'code' => strtolower($data['code']), 'name' => $data['name'], 'adapter' => $data['adapter'],
            'environment' => $data['environment'], 'status' => $data['status'],
            'credentials_encrypted' => $data['credentials'], 'settings' => $data['settings'],
        ]);
        $audit->record('provider.created', null, Provider::class, (string) $provider->id, 'Provider adapter configured', [
            'code' => $provider->code, 'environment' => $provider->environment, 'status' => $provider->status,
        ]);
        return response()->json(['data' => ['id' => $provider->id, 'code' => $provider->code, 'status' => $provider->status]], 201);
    }

    public function storeService(Request $request, AuditLogger $audit): JsonResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $data = $request->validate([
            'service_code' => ['required', 'alpha_dash:ascii', 'max:64', 'unique:service_catalogue,service_code'],
            'category' => ['required', Rule::in(['airtime', 'data', 'electricity', 'cable', 'exam_pin', 'gift_card'])],
            'name' => ['required', 'string', 'max:120'],
            'status' => ['required', Rule::in(['inactive'])],
            'validation_schema' => ['required', 'array', 'min:1'],
        ]);
        $id = DB::table('service_catalogue')->insertGetId([
            'service_code' => strtolower($data['service_code']), 'category' => $data['category'], 'name' => $data['name'],
            'status' => $data['status'], 'validation_schema' => json_encode($data['validation_schema']),
            'metadata' => json_encode([]), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $audit->record('service.created', null, 'service_catalogue', (string) $id, 'Service catalogue item configured', ['service_code' => $data['service_code']]);
        return response()->json(['data' => ['id' => $id, 'service_code' => $data['service_code']]], 201);
    }

    public function storeMapping(Request $request, AuditLogger $audit): JsonResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $data = $request->validate([
            'provider_id' => ['required', 'integer', 'exists:providers,id'],
            'service_catalogue_id' => ['required', 'integer', 'exists:service_catalogue,id'],
            'provider_product_code' => ['required', 'string', 'max:100'],
            'status' => ['required', Rule::in(['inactive'])],
            'priority' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mapping.field_map' => ['nullable', 'array'],
        ]);
        $id = DB::table('provider_services')->insertGetId([
            'provider_id' => $data['provider_id'], 'service_catalogue_id' => $data['service_catalogue_id'],
            'provider_product_code' => $data['provider_product_code'], 'status' => $data['status'], 'priority' => $data['priority'] ?? 100,
            'mapping' => json_encode($data['mapping'] ?? []), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $audit->record('provider.mapping_created', null, 'provider_services', (string) $id, 'Provider service mapping configured');
        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function storePricing(Request $request, AuditLogger $audit): JsonResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $data = $request->validate([
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'service_catalogue_id' => ['required', 'integer', 'exists:service_catalogue,id'],
            'cost_minor' => ['required', 'integer', 'min:0'], 'sell_minor' => ['required', 'integer', 'min:1'],
            'floor_minor' => ['nullable', 'integer', 'min:1'], 'ceiling_minor' => ['nullable', 'integer', 'min:1'],
            'currency' => ['required', Rule::in(['NGN'])], 'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after:effective_from'],
        ]);
        abort_if($data['cost_minor'] > $data['sell_minor'], 422, 'Cost cannot exceed sell price.');
        $tenantId = $data['tenant_id'] ?? null;
        $overlap = DB::table('pricing_rules')->where('service_catalogue_id', $data['service_catalogue_id'])->where('status', 'active')->where(function ($q) use ($tenantId) { $tenantId === null ? $q->whereNull('tenant_id') : $q->where('tenant_id', $tenantId); })->where(function ($q) use ($data) { $q->whereNull('effective_until')->orWhere('effective_until', '>', $data['effective_from']); })->exists();
        abort_if($overlap, 422, 'An active pricing rule overlaps this effective period.');
        abort_if(isset($data['floor_minor']) && $data['sell_minor'] < $data['floor_minor'], 422, 'Sell price is below the configured floor.');
        abort_if(isset($data['ceiling_minor']) && $data['sell_minor'] > $data['ceiling_minor'], 422, 'Sell price exceeds the configured ceiling.');
        $id = DB::table('pricing_rules')->insertGetId($data + [
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $audit->record('pricing_rule.created', $tenantId, 'pricing_rules', (string) $id, 'Pricing rule created', [
            'service_catalogue_id' => $data['service_catalogue_id'], 'cost_minor' => $data['cost_minor'], 'sell_minor' => $data['sell_minor'],
        ]);
        return response()->json(['data' => ['id' => $id]], 201);
    }
}
