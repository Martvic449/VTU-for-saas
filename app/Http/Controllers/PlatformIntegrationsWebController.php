<?php
namespace App\Http\Controllers;
use App\Models\Provider;
use App\Services\AuditLogger;
use App\Services\Webhooks\WebhookUrlPolicy;
use InvalidArgumentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
final class PlatformIntegrationsWebController
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['super-admin','operations-admin']), 403);
        return view('platform.integrations', [
            'providers' => Provider::with('services.service')->orderBy('name')->get(),
            'services' => DB::table('service_catalogue')->orderBy('category')->orderBy('name')->get(),
            'tenants' => DB::table('tenants')->orderBy('name')->get(),
            'mappings' => DB::table('provider_services')->join('providers','providers.id','=','provider_services.provider_id')->join('service_catalogue','service_catalogue.id','=','provider_services.service_catalogue_id')->select('provider_services.*','providers.name as provider_name','service_catalogue.name as service_name')->orderBy('provider_services.priority')->get(),
            'pricing' => DB::table('pricing_rules')->join('service_catalogue','service_catalogue.id','=','pricing_rules.service_catalogue_id')->leftJoin('tenants','tenants.id','=','pricing_rules.tenant_id')->leftJoin('provider_services as ps','ps.id','=','pricing_rules.provider_service_id')->leftJoin('providers as p','p.id','=','ps.provider_id')->select('pricing_rules.*','service_catalogue.name as service_name','tenants.name as tenant_name','p.name as provider_name')->orderByDesc('pricing_rules.effective_from')->limit(100)->get(),
        ]);
    }

    public function createProvider(Request $request, AuditLogger $audit, WebhookUrlPolicy $urlPolicy): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $data = $request->validate([
            'code' => ['required','alpha_dash:ascii','max:64','unique:providers,code'],
            'name' => ['required','string','max:120'], 'environment' => ['required',Rule::in(['sandbox','live'])],
            'adapter' => ['required',Rule::in(['vtpass','smeplug','configured_http'])],
            'api_key' => ['nullable','string','max:2000'], 'public_key' => ['nullable','string','max:2000'],
            'secret_key' => ['nullable','string','max:2000'], 'api_token' => ['nullable','string','max:2000'],
            'purchase_url' => ['nullable','url','starts_with:https://'], 'requery_url' => ['nullable','url','starts_with:https://'],
            'settings_json' => ['nullable','json'],
        ]);
        $credentials = array_filter(['api_key' => $data['api_key'] ?? null, 'public_key' => $data['public_key'] ?? null,
            'secret_key' => $data['secret_key'] ?? null, 'api_token' => $data['api_token'] ?? null], fn ($value) => is_string($value) && $value !== '');
        $settings = isset($data['settings_json']) && trim($data['settings_json']) !== '' ? json_decode($data['settings_json'], true) : [];
        abort_unless(is_array($settings), 422, 'Provider settings must be a JSON object.');
        if ($data['adapter'] === 'vtpass') {
            abort_unless(isset($credentials['api_key'], $credentials['public_key'], $credentials['secret_key']), 422, 'VTpass requires API key, public key and secret key.');
            $settings['base_url'] = $data['environment'] === 'live' ? 'https://vtpass.com/api' : 'https://sandbox.vtpass.com/api';
            $settings['service_ids'] = $settings['service_ids'] ?? [];
        } elseif ($data['adapter'] === 'smeplug') {
            abort_unless(isset($credentials['api_token']), 422, 'SMEPlug requires the private key from your SMEPlug account.');
            // Save the provider inactive even before response examples are available. This allows
            // read-only network/plan discovery; update status validation blocks activation until
            // exact response paths and terminal values have been captured from the sandbox.
        } else {
            $settings['purchase_url'] = $data['purchase_url'] ?? ($settings['purchase_url'] ?? null);
            $settings['requery_url'] = $data['requery_url'] ?? ($settings['requery_url'] ?? null);
            abort_unless(isset($credentials['api_token']), 422, 'Configured HTTP requires an API token.');
            foreach (['purchase_url', 'requery_url'] as $field) {
                abort_unless(is_string($settings[$field] ?? null) && str_starts_with($settings[$field], 'https://'), 422, 'Custom provider settings must include verified HTTPS '.$field.'.');
                try { $urlPolicy->assertPublicHttps($settings[$field]); }
                catch (InvalidArgumentException $exception) { return back()->withErrors([$field => $exception->getMessage()])->withInput(); }
            }
            abort_unless(is_string($settings['response_status_path'] ?? null) && $settings['response_status_path'] !== ''
                && ! empty($settings['purchase_fields']) && ! empty($settings['requery_fields'])
                && ! empty($settings['success_statuses']) && ! empty($settings['failed_statuses']), 422, 'Configured HTTP requires exact request/response mappings and terminal status values.');
        }
        $provider = Provider::create(['code' => strtolower($data['code']), 'name' => $data['name'], 'adapter' => $data['adapter'],
            'environment' => $data['environment'], 'status' => 'inactive', 'credentials_encrypted' => $credentials, 'settings' => $settings]);
        $audit->record('provider.created', null, Provider::class, (string) $provider->id, 'Provider configured inactive', ['environment' => $provider->environment, 'adapter' => $provider->adapter]);
        return back()->with('status', 'Provider saved inactive. Complete official sandbox tests and confirm request/response mappings before activation.');
    }

    public function createService(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'),403);
        $data=$request->validate(['service_code'=>['required','alpha_dash:ascii','max:64','unique:service_catalogue,service_code'],'category'=>['required',Rule::in(['airtime','data','electricity','cable','exam_pin','gift_card'])],'name'=>['required','string','max:120'],'validation_schema_json'=>['required','json']]);
        $schemaObject=json_decode($data['validation_schema_json']);
        abort_unless(is_object($schemaObject)&&count((array)$schemaObject)>0,422,'Validation schema must be a non-empty JSON object.');
        $schema=(array)$schemaObject;
        $id=DB::table('service_catalogue')->insertGetId(['service_code'=>strtolower($data['service_code']),'category'=>$data['category'],'name'=>$data['name'],'status'=>'inactive','validation_schema'=>json_encode($schema),'metadata'=>json_encode([]),'created_at'=>now(),'updated_at'=>now()]);
        $audit->record('service.created',null,'service_catalogue',(string)$id,'Service created inactive');
        return back()->with('status','Service created inactive. Configure provider mapping and pricing before activation.');
    }

    public function createMapping(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'),403);
        $data=$request->validate(['provider_id'=>['required','exists:providers,id'],'service_catalogue_id'=>['required','exists:service_catalogue,id'],'provider_product_code'=>['required','string','max:100'],'priority'=>['required','integer','min:1','max:65535'],'mapping_json'=>['nullable','json']]);
        $mappingObject = isset($data['mapping_json']) && trim($data['mapping_json']) !== '' ? json_decode($data['mapping_json']) : (object) [];
        abort_unless(is_object($mappingObject),422,'Provider mapping must be a JSON object.');
        $mapping = (array) $mappingObject;
        DB::table('provider_services')->insert(['provider_id'=>$data['provider_id'],'service_catalogue_id'=>$data['service_catalogue_id'],'provider_product_code'=>$data['provider_product_code'],'status'=>'inactive','priority'=>$data['priority'],'mapping'=>json_encode($mapping),'created_at'=>now(),'updated_at'=>now()]);
        $audit->record('provider.mapping_created',null,'provider_services',null,'Provider mapping created inactive');
        return back()->with('status','Provider mapping created inactive.');
    }

    public function updateStatus(Request $request, string $type, int $id, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['active','inactive'])], 'reason' => ['required','string','min:8','max:500'], 'environment_verified' => ['nullable','accepted']]);
        $tables = ['provider' => 'providers', 'service' => 'service_catalogue', 'mapping' => 'provider_services', 'pricing' => 'pricing_rules'];
        abort_unless(isset($tables[$type]), 404);
        $table = $tables[$type];
        $record = DB::table($table)->where('id',$id)->first();
        abort_unless($record,404);
        if ($type === 'provider' && $data['status'] === 'active') {
            abort_unless($record->credentials_encrypted && $record->settings, 409, 'Provider credentials and endpoint settings are required.');
            abort_unless($record->environment === config('providers.environment', 'sandbox'), 409, 'Provider environment does not match the platform execution environment.');
            if ($record->adapter === 'smeplug') {
                $settings = is_array($record->settings) ? $record->settings : (json_decode($record->settings ?: '{}', true) ?: []);
                abort_unless(! empty($settings['response_status_path']) && ! empty($settings['response_reference_path'])
                    && ! empty($settings['success_statuses']) && ! empty($settings['failed_statuses']), 409,
                    'SMEPlug response mapping is incomplete. Obtain a real sandbox response and configure exact status/reference paths and terminal values before activation.');
            }
            abort_unless(($data['environment_verified'] ?? null) === '1' || ($data['environment_verified'] ?? null) === true, 422, 'Confirm that this provider environment has been tested against the supplier documentation.');
        }
        if ($type === 'mapping' && $data['status'] === 'active') {
            $provider = Provider::query()->where('id', $record->provider_id)->where('status','active')->where('environment',config('providers.environment','sandbox'))->first();
            abort_unless($provider, 409, 'Activate a provider in the configured environment before activating its mapping.');
            $mapping = is_array($record->mapping) ? $record->mapping : (json_decode($record->mapping ?: '{}', true) ?: []);
            if ($provider->adapter === 'vtpass') {
                $serviceCode = (string) DB::table('service_catalogue')->where('id', $record->service_catalogue_id)->value('service_code');
                $serviceId = $mapping['service_id'] ?? data_get($provider->settings, 'service_ids.'.$serviceCode);
                abort_unless(is_string($serviceId) && $serviceId !== '', 422, 'VTpass mapping requires the exact service_id from the official VTpass catalogue.');
            } elseif ($provider->adapter === 'smeplug') {
                abort_unless(isset($mapping['network_id']) && is_numeric($mapping['network_id']) && (int) $mapping['network_id'] > 0, 422,
                    'SMEPlug mapping requires the documented network_id (1=MTN, 2=Airtel, 3=9Mobile, 4=Glo).');
                $category = DB::table('service_catalogue')->where('id', $record->service_catalogue_id)->value('category');
                if ($category === 'data') abort_unless(isset($mapping['plan_id']) && is_numeric($mapping['plan_id']) && (int) $mapping['plan_id'] > 0, 422,
                    'SMEPlug data mapping requires the exact plan_id from the supplier data-plans endpoint.');
                if (isset($mapping['smeplug_endpoint'])) abort_unless(in_array($mapping['smeplug_endpoint'], ['data/purchase','airtime/purchase','vtu'], true), 422, 'Unsupported SMEPlug endpoint.');
            }
        }
        if ($type === 'service' && $data['status'] === 'active') {
            $mapped = DB::table('provider_services')->join('providers','providers.id','=','provider_services.provider_id')->where('service_catalogue_id',$id)->where('provider_services.status','active')->where('providers.status','active')->where('providers.environment',config('providers.environment','sandbox'))->exists();
            $priced = DB::table('pricing_rules')->where('service_catalogue_id',$id)->whereNull('tenant_id')->where('status','active')->where('effective_from','<=',now())->where(function($q){$q->whereNull('effective_until')->orWhere('effective_until','>',now());})->exists();
            abort_unless($mapped && $priced, 409, 'A service needs an active provider mapping and effective platform price before activation.');
        }
        if ($type === 'pricing' && $data['status'] === 'active') {
            $overlap = DB::table('pricing_rules')->where('service_catalogue_id',$record->service_catalogue_id)->where('id','!=',$id)->where('status','active')
                ->where(function($q)use($record){$record->tenant_id===null?$q->whereNull('tenant_id'):$q->where('tenant_id',$record->tenant_id);})
                ->where(function($q)use($record){$record->provider_service_id===null?$q->whereNull('provider_service_id'):$q->where('provider_service_id',$record->provider_service_id);})
                ->where(function($q)use($record){$q->whereNull('effective_until')->orWhere('effective_until','>',$record->effective_from); })->exists();
            abort_if($overlap,409,'Another active pricing rule overlaps this rule.');
        }
        $before = $record->status;
        DB::table($table)->where('id',$id)->update(['status'=>$data['status'],'updated_at'=>now()]);
        $audit->record($type.'.status_changed',null,$table,(string)$id,$data['reason'],['before'=>$before,'after'=>$data['status'],'environment_verified'=>($data['environment_verified'] ?? false)]);
        return back()->with('status', ucfirst($type).' status updated.');
    }

    public function createPricing(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'),403);
        $data=$request->validate(['tenant_id'=>['nullable','integer','exists:tenants,id'],'service_catalogue_id'=>['required','integer','exists:service_catalogue,id'],'provider_service_id'=>['nullable','integer','exists:provider_services,id'],'cost_minor'=>['required','integer','min:0'],'sell_minor'=>['required','integer','min:1'],'floor_minor'=>['nullable','integer','min:1'],'ceiling_minor'=>['nullable','integer','min:1'],'effective_from'=>['required','date']]);
        abort_if($data['cost_minor']>$data['sell_minor'],422,'Cost cannot exceed sell price.');
        abort_if(isset($data['floor_minor']) && $data['sell_minor'] < $data['floor_minor'],422,'Sell price is below the floor.');
        abort_if(isset($data['ceiling_minor']) && $data['sell_minor'] > $data['ceiling_minor'],422,'Sell price exceeds the ceiling.');
        $tenantId = $data['tenant_id'] ?? null;
        $providerServiceId = $data['provider_service_id'] ?? null;
        if ($providerServiceId !== null) abort_unless(DB::table('provider_services')->where('id',$providerServiceId)->where('service_catalogue_id',$data['service_catalogue_id'])->exists(),422,'The supplier mapping must belong to the selected service.');
        $overlap=DB::table('pricing_rules')->where('service_catalogue_id',$data['service_catalogue_id'])->where('status','active')->where(function($q)use($tenantId){$tenantId===null?$q->whereNull('tenant_id'):$q->where('tenant_id',$tenantId);})->where(function($q)use($providerServiceId){$providerServiceId===null?$q->whereNull('provider_service_id'):$q->where('provider_service_id',$providerServiceId);})->where(function($q)use($data){$q->whereNull('effective_until')->orWhere('effective_until','>',$data['effective_from']);})->exists();
        abort_if($overlap,422,'An active pricing rule already overlaps this effective period for this service and tenant. End or deactivate the existing rule first.');
        DB::table('pricing_rules')->insert($data+['currency'=>'NGN','status'=>'active','effective_until'=>null,'created_at'=>now(),'updated_at'=>now()]);
        $audit->record('pricing_rule.created',$tenantId,'pricing_rules',null,'Pricing rule created',['service_catalogue_id'=>$data['service_catalogue_id'],'sell_minor'=>$data['sell_minor']]);
        return back()->with('status','Pricing rule created.');
    }

    public function updateProvider(Request $request, int $provider, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $record = Provider::findOrFail($provider);
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'environment' => ['required',Rule::in(['sandbox','live'])],
            'api_key' => ['nullable','string','max:2000'], 'public_key' => ['nullable','string','max:2000'],
            'secret_key' => ['nullable','string','max:2000'], 'api_token' => ['nullable','string','max:2000'],
            'settings_json' => ['nullable','json'],
        ]);
        $credentials = $record->credentials_encrypted ?? [];
        foreach (['api_key','public_key','secret_key','api_token'] as $key) {
            if (isset($data[$key]) && $data[$key] !== '') $credentials[$key] = $data[$key];
        }
        $settings = $record->settings ?? [];
        if (isset($data['settings_json']) && trim($data['settings_json']) !== '') {
            $decoded = json_decode($data['settings_json'], true);
            abort_unless(is_array($decoded), 422, 'Settings must be a JSON object.');
            $settings = array_replace_recursive($settings, $decoded);
        }
        if ($record->adapter === 'vtpass') {
            abort_unless(isset($credentials['api_key'],$credentials['public_key'],$credentials['secret_key']), 422, 'VTpass requires API key, public key and secret key.');
            $settings['base_url'] = $data['environment'] === 'live' ? 'https://vtpass.com/api' : 'https://sandbox.vtpass.com/api';
        } elseif ($record->adapter === 'smeplug') {
            abort_unless(isset($credentials['api_token']), 422, 'SMEPlug private key is required.');
        }
        $record->forceFill(['name'=>$data['name'],'environment'=>$data['environment'],'credentials_encrypted'=>$credentials,
            'settings'=>$settings,'status'=>'inactive','last_health_check_at'=>null])->save();
        $audit->record('provider.updated', null, Provider::class, (string)$record->id, 'Provider credentials or configuration updated; provider returned to inactive state', ['environment'=>$record->environment]);
        return back()->with('status','Provider updated and deactivated. Run a fresh sandbox catalogue check before reactivation.');
    }

    public function inspectSupplierCatalogue(Request $request, int $provider, string $resource, \App\Services\Providers\SmeplugReadOnlyClient $client, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        abort_unless(in_array($resource, ['networks','data-plans'], true), 404);
        $record = Provider::findOrFail($provider);
        abort_unless($record->adapter === 'smeplug', 422, 'Read-only catalogue discovery is currently supported for SMEPlug connections.');
        abort_unless($record->environment === 'sandbox', 409, 'Supplier catalogue discovery is restricted to sandbox credentials.');
        try {
            $payload = $client->get($record, $resource);
            $record->forceFill(['last_health_check_at'=>now()])->save();
            $audit->record('provider.catalogue_checked', null, Provider::class, (string)$record->id, 'Read-only supplier '.$resource.' catalogue request completed', ['resource'=>$resource,'http_success'=>true]);
            return back()->with('supplier_catalogue', ['provider'=>$record->name,'resource'=>$resource,'payload'=>$payload]);
        } catch (\Throwable $exception) {
            $audit->record('provider.catalogue_check_failed', null, Provider::class, (string)$record->id, 'Read-only supplier catalogue request failed', ['resource'=>$resource,'error'=>substr($exception->getMessage(),0,240)]);
            return back()->withErrors(['supplier_catalogue'=>'Supplier request failed: '.$exception->getMessage()]);
        }
    }

    public function updateService(Request $request, int $service, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $data = $request->validate(['name'=>['required','string','max:120'],'validation_schema_json'=>['required','json']]);
        $schema = json_decode($data['validation_schema_json'], true);
        abort_unless(is_array($schema) && count($schema)>0, 422, 'Validation schema must be a non-empty JSON object.');
        $record = DB::table('service_catalogue')->where('id',$service)->first();
        abort_unless($record,404);
        DB::table('service_catalogue')->where('id',$service)->update(['name'=>$data['name'],'validation_schema'=>json_encode($schema),'status'=>'inactive','updated_at'=>now()]);
        $audit->record('service.updated',null,'service_catalogue',(string)$service,'Service definition updated and returned to inactive state');
        return back()->with('status','Service definition updated. Recheck mapping and pricing before reactivation.');
    }

    public function updateMapping(Request $request, int $mapping, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $data = $request->validate(['provider_product_code'=>['required','string','max:100'],'priority'=>['required','integer','min:1','max:65535'],'mapping_json'=>['nullable','json']]);
        $object = isset($data['mapping_json']) && trim($data['mapping_json']) !== '' ? json_decode($data['mapping_json']) : (object)[];
        abort_unless(is_object($object),422,'Supplier mapping must be a JSON object.');
        $record = DB::table('provider_services')->where('id',$mapping)->first();
        abort_unless($record,404);
        DB::table('provider_services')->where('id',$mapping)->update(['provider_product_code'=>$data['provider_product_code'],'priority'=>$data['priority'],'mapping'=>json_encode((array)$object),'status'=>'inactive','updated_at'=>now()]);
        $audit->record('provider.mapping_updated',null,'provider_services',(string)$mapping,'Supplier product mapping updated and deactivated');
        return back()->with('status','Supplier product mapping updated and deactivated. Revalidate it before activation.');
    }

    public function closePricingRule(Request $request, int $pricing, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $data = $request->validate(['effective_until'=>['required','date']]);
        $record = DB::table('pricing_rules')->where('id',$pricing)->first();
        abort_unless($record,404);
        abort_if($record->status !== 'active', 409, 'Only active pricing rules can be ended.');
        abort_if($data['effective_until'] <= $record->effective_from, 422, 'The end date must be after the rule start date.');
        DB::table('pricing_rules')->where('id',$pricing)->update(['effective_until'=>$data['effective_until'],'updated_at'=>now()]);
        $audit->record('pricing_rule.ended',$record->tenant_id,'pricing_rules',(string)$pricing,'Pricing rule given an end date',['effective_until'=>$data['effective_until']]);
        return back()->with('status','Pricing rule end date saved. Previous pricing remains in the audit/history record.');
    }

}
