@extends('layouts.app')
@section('title','Providers and catalogue')
@section('content')
<div class="wrap">
    <div class="row"><div><h1 class="title">Providers, services and pricing</h1><p class="muted">Configure upstream connections and the service catalogue. New integrations are inactive until deliberately verified and enabled.</p></div><a class="btn secondary" href="{{ route('platform.dashboard') }}">Back to overview</a></div>
    @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
    @if(auth()->user()->hasRole('super-admin'))
    <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr));align-items:start">
        <section class="card"><h3 style="margin-top:0">Add provider connection</h3><p class="muted">Use supplier-issued sandbox credentials and official API URLs. Credentials are encrypted at rest.</p>
            <form method="post" action="{{ route('platform.integrations.providers') }}">@csrf
                <div class="field"><label>Provider code</label><input name="code" required placeholder="supplier_one"></div>
                <div class="field"><label>Display name</label><input name="name" required></div>
                <div class="field"><label>Environment</label><select name="environment"><option value="sandbox">Sandbox</option><option value="live">Live (not selected until configured)</option></select></div>
                <div class="field"><label>Supplier adapter</label><select name="adapter" required><option value="vtpass">VTpass</option><option value="smeplug">SMEPlug v1 (official Postman collection)</option><option value="configured_http">Custom JSON/HTTPS adapter</option></select></div>
                <div class="field"><label>API key (VTpass)</label><input name="api_key" type="password" autocomplete="new-password"></div>
                <div class="field"><label>Public key (VTpass GET)</label><input name="public_key" type="password" autocomplete="new-password"></div>
                <div class="field"><label>Secret key (VTpass POST)</label><input name="secret_key" type="password" autocomplete="new-password"></div>
                <div class="field"><label>Private key / API token (SMEPlug/custom)</label><input name="api_token" type="password" autocomplete="new-password"></div>
                <div class="field"><label>Purchase endpoint (SMEPlug/custom HTTPS)</label><input name="purchase_url" type="url"></div>
                <div class="field"><label>Requery endpoint (SMEPlug/custom HTTPS)</label><input name="requery_url" type="url"></div>
                <div class="field"><label>Supplier settings JSON</label><textarea name="settings_json" rows="4" placeholder='{"response_status_path":"status","success_statuses":["success"],"failed_statuses":["failed"]}'></textarea><small class="muted">SMEPlug uses https://smeplug.ng/api/v1 automatically. Configure response_status_path, response_reference_path, success_statuses and failed_statuses only after obtaining an actual sandbox response. For each mapping use network_id; data plans also require plan_id; endpoint may be data/purchase, airtime/purchase or vtu.</small></div>
                <button class="btn" type="submit">Save provider inactive</button>
            </form>
        </section>
        <section class="card"><h3 style="margin-top:0">Add service catalogue item</h3>
            <form method="post" action="{{ route('platform.integrations.services') }}">@csrf
                <div class="field"><label>Service code</label><input name="service_code" required placeholder="airtime"></div>
                <div class="field"><label>Category</label><select name="category"><option>airtime</option><option>data</option><option>electricity</option><option>cable</option><option>exam_pin</option><option>gift_card</option></select></div>
                <div class="field"><label>Service name</label><input name="name" required></div>
                <div class="field"><label>Validation schema (Laravel rules JSON)</label><textarea name="validation_schema_json" rows="5" required placeholder='{"phone":"required|string","network":"required|string"}'></textarea></div>
                <button class="btn" type="submit">Create service inactive</button>
            </form>
        </section>
        <section class="card"><h3 style="margin-top:0">Map provider product</h3>
            <form method="post" action="{{ route('platform.integrations.mappings') }}">@csrf
                <div class="field"><label>Provider</label><select name="provider_id" required>@foreach($providers as $provider)<option value="{{ $provider->id }}">{{ $provider->name }} ({{ $provider->environment }})</option>@endforeach</select></div>
                <div class="field"><label>Service</label><select name="service_catalogue_id" required>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->name }} ({{ $service->service_code }})</option>@endforeach</select></div>
                <div class="field"><label>Supplier product code</label><input name="provider_product_code" required></div>
                <div class="field"><label>Routing priority (lower first)</label><input name="priority" type="number" min="1" value="100" required></div>
                <div class="field"><label>Supplier mapping JSON (optional)</label><textarea name="mapping_json" rows="3" placeholder="{&quot;network_id&quot;:1,&quot;plan_id&quot;:123,&quot;smeplug_endpoint&quot;:&quot;data/purchase&quot;}"></textarea></div>
                <button class="btn" type="submit">Create mapping inactive</button>
            </form>
        </section>
        <section class="card"><h3 style="margin-top:0">Add platform pricing</h3><p class="muted">Amounts are integer kobo, not naira or decimal floats.</p>
            <form method="post" action="{{ route('platform.integrations.pricing') }}">@csrf
                <div class="field"><label>Service</label><select name="service_catalogue_id" required>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->name }} ({{ $service->service_code }})</option>@endforeach</select></div>
                <div class="field"><label>Tenant pricing (optional)</label><select name="tenant_id"><option value="">Platform-wide</option>@foreach($tenants as $tenant)<option value="{{ $tenant->id }}">{{ $tenant->name }}</option>@endforeach</select></div>
                <div class="field"><label>Supplier-specific cost rule (optional)</label><select name="provider_service_id"><option value="">Generic service cost</option>@foreach($mappings as $mapping)<option value="{{ $mapping->id }}">{{ $mapping->provider_name }} — {{ $mapping->service_name }} — {{ $mapping->provider_product_code }}</option>@endforeach</select></div>
                <div class="field"><label>Cost (kobo)</label><input name="cost_minor" type="number" min="0" required></div>
                <div class="field"><label>Sell price (kobo)</label><input name="sell_minor" type="number" min="1" required></div>
                <div class="field"><label>Effective from</label><input name="effective_from" type="datetime-local" required value="{{ now()->format('Y-m-d\\TH:i') }}"></div>
                <button class="btn" type="submit">Save platform pricing</button>
            </form>
        </section>
    </div>
    @endif
    <div class="card" style="margin:18px 0"><h3 style="margin-top:0">Configured providers</h3><div class="table-wrap"><table><thead><tr><th>Name</th><th>Adapter</th><th>Code</th><th>Environment</th><th>Status</th><th>Mapped services</th><th>Control</th></tr></thead><tbody>@forelse($providers as $provider)<tr><td>{{ $provider->name }}</td><td>{{ $provider->adapter }}</td><td>{{ $provider->code }}</td><td>{{ $provider->environment }}</td><td><span class="pill {{ $provider->status }}">{{ $provider->status }}</span></td><td>{{ $provider->services->count() }}</td><td>@if(auth()->user()->hasRole('super-admin'))<form method="post" action="{{ route('platform.integrations.status',['type'=>'provider','id'=>$provider->id]) }}">@csrf @method('PATCH')<input name="reason" required minlength="8" maxlength="500" placeholder="Reason">@if($provider->status!=='active')<label style="font-size:12px"><input type="checkbox" name="environment_verified" value="1" required> I verified the supplier environment and response mapping in its sandbox</label>@endif<input type="hidden" name="status" value="{{ $provider->status==='active'?'inactive':'active' }}"><button class="btn secondary" type="submit">{{ $provider->status==='active'?'Deactivate':'Activate' }}</button></form>@endif</td></tr>@empty<tr><td colspan="7">No provider connections configured.</td></tr>@endforelse</tbody></table></div></div>
    <div class="card" style="margin:18px 0"><h3 style="margin-top:0">Service catalogue</h3><div class="table-wrap"><table><thead><tr><th>Service</th><th>Code</th><th>Category</th><th>Status</th><th>Control</th></tr></thead><tbody>@forelse($services as $service)<tr><td>{{ $service->name }}</td><td>{{ $service->service_code }}</td><td>{{ $service->category }}</td><td><span class="pill {{ $service->status }}">{{ $service->status }}</span></td><td>@if(auth()->user()->hasRole('super-admin'))<form method="post" action="{{ route('platform.integrations.status',['type'=>'service','id'=>$service->id]) }}">@csrf @method('PATCH')<input name="reason" required minlength="8" maxlength="500" placeholder="Reason"><input type="hidden" name="status" value="{{ $service->status==='active'?'inactive':'active' }}"><button class="btn secondary" type="submit">{{ $service->status==='active'?'Deactivate':'Activate' }}</button></form>@endif</td></tr>@empty<tr><td colspan="5">No services configured.</td></tr>@endforelse</tbody></table></div></div>
    <div class="card" style="margin:18px 0"><h3 style="margin-top:0">Provider mappings</h3><div class="table-wrap"><table><thead><tr><th>Provider</th><th>Service</th><th>Supplier product</th><th>Priority</th><th>Status</th><th>Control</th></tr></thead><tbody>@forelse($mappings as $mapping)<tr><td>{{ $mapping->provider_name }}</td><td>{{ $mapping->service_name }}</td><td>{{ $mapping->provider_product_code }}</td><td>{{ $mapping->priority }}</td><td><span class="pill {{ $mapping->status }}">{{ $mapping->status }}</span></td><td>@if(auth()->user()->hasRole('super-admin'))<form method="post" action="{{ route('platform.integrations.status',['type'=>'mapping','id'=>$mapping->id]) }}">@csrf @method('PATCH')<input name="reason" required minlength="8" maxlength="500" placeholder="Reason"><input type="hidden" name="status" value="{{ $mapping->status==='active'?'inactive':'active' }}"><button class="btn secondary" type="submit">{{ $mapping->status==='active'?'Deactivate':'Activate' }}</button></form>@endif</td></tr>@empty<tr><td colspan="6">No mappings configured.</td></tr>@endforelse</tbody></table></div></div>
    <div class="card" style="margin:18px 0"><h3 style="margin-top:0">Current pricing rules</h3><div class="table-wrap"><table><thead><tr><th>Service</th><th>Tenant</th><th>Supplier cost rule</th><th>Cost</th><th>Sell</th><th>Effective period</th><th>Status</th><th>Control</th></tr></thead><tbody>@forelse($pricing as $rule)<tr><td>{{ $rule->service_name }}</td><td>{{ $rule->tenant_name ?? 'Platform' }}</td><td>{{ $rule->provider_name ?? 'Generic / fallback' }}</td><td>₦{{ number_format($rule->cost_minor/100,2) }}</td><td>₦{{ number_format($rule->sell_minor/100,2) }}</td><td>{{ $rule->effective_from }} — {{ $rule->effective_until ?? 'No end date' }}</td><td><span class="pill {{ $rule->status }}">{{ $rule->status }}</span></td><td>@if(auth()->user()->hasRole('super-admin'))<form method="post" action="{{ route('platform.integrations.status',['type'=>'pricing','id'=>$rule->id]) }}">@csrf @method('PATCH')<input name="reason" required minlength="8" maxlength="500" placeholder="Reason"><input type="hidden" name="status" value="{{ $rule->status==='active'?'inactive':'active' }}"><button class="btn secondary" type="submit">{{ $rule->status==='active'?'Deactivate':'Activate' }}</button></form>@if($rule->status==='active' && !$rule->effective_until)<form method="post" action="{{ route('platform.integrations.pricing.end',$rule->id) }}" style="margin-top:6px">@csrf @method('PATCH')<label>End this price rule</label><input type="datetime-local" name="effective_until" min="{{ now()->format('Y-m-d\\TH:i') }}" required><button class="btn secondary" type="submit">Save end date</button></form>@endif @endif</td></tr>@empty<tr><td colspan="8">No pricing rules configured.</td></tr>@endforelse</tbody></table></div></div>

    @if(session('supplier_catalogue'))
    @php($catalogue = session('supplier_catalogue'))
    <section class="card" style="margin:18px 0">
        <h3>Supplier catalogue — {{ $catalogue['provider'] }} / {{ $catalogue['resource'] }}</h3>
        <p class="muted">Sandbox snapshot retrieved {{ $catalogue['fetched_at'] }}. {{ count($catalogue['items']) }} actual supplier items. Mapping a selection creates an inactive mapping; it does not activate products or enable purchases.</p>
        @if($catalogue['adapter']==='vtpass' && $catalogue['resource']==='service-categories')
            <div class="table-wrap"><table><thead><tr><th>Category</th><th>Identifier</th><th>Next step</th></tr></thead><tbody>
            @foreach($catalogue['items'] as $item)<tr><td>{{ $item['name'] ?? '—' }}</td><td>{{ $item['identifier'] ?? '—' }}</td><td><form method="post" action="{{ route('platform.integrations.providers.catalogue',['provider'=>$catalogue['provider_id'],'resource'=>'services']) }}">@csrf<input type="hidden" name="identifier" value="{{ $item['identifier'] ?? '' }}"><button class="btn secondary" type="submit">Fetch services</button></form></td></tr>@endforeach
            </tbody></table></div>
        @elseif($catalogue['adapter']==='vtpass' && $catalogue['resource']==='services')
            <div class="table-wrap"><table><thead><tr><th>Supplier service</th><th>Service ID</th><th>Product type</th><th>Next step</th><th>Map service</th></tr></thead><tbody>
            @foreach($catalogue['items'] as $index=>$item)<tr><td>{{ $item['name'] ?? '—' }}</td><td>{{ $item['serviceID'] ?? '—' }}</td><td>{{ $item['product_type'] ?? '—' }}</td><td><form method="post" action="{{ route('platform.integrations.providers.catalogue',['provider'=>$catalogue['provider_id'],'resource'=>'service-variations']) }}">@csrf<input type="hidden" name="identifier" value="{{ $item['serviceID'] ?? '' }}"><button class="btn secondary" type="submit">Fetch variations</button></form></td><td><form method="post" action="{{ route('platform.integrations.providers.catalogue.map',$catalogue['provider_id']) }}">@csrf<input type="hidden" name="item_index" value="{{ $index }}"><select name="service_catalogue_id" required><option value="">Choose platform service</option>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->name }} ({{ $service->service_code }})</option>@endforeach</select><input type="number" name="priority" min="1" max="65535" value="100" required><button class="btn secondary" type="submit">Create inactive mapping</button></form></td></tr>@endforeach
            </tbody></table></div>
        @elseif($catalogue['adapter']==='vtpass' && $catalogue['resource']==='service-variations')
            <div class="table-wrap"><table><thead><tr><th>Plan / variation</th><th>Variation code</th><th>Supplier amount</th><th>Fixed price</th><th>Map to platform service</th></tr></thead><tbody>
            @foreach($catalogue['items'] as $index=>$item)<tr><td>{{ $item['name'] ?? '—' }}</td><td>{{ $item['variation_code'] ?? '—' }}</td><td>{{ isset($item['variation_amount']) ? '₦'.number_format((float)$item['variation_amount'],2) : '—' }}</td><td>{{ $item['fixedPrice'] ?? '—' }}</td><td><form method="post" action="{{ route('platform.integrations.providers.catalogue.map',$catalogue['provider_id']) }}">@csrf<input type="hidden" name="item_index" value="{{ $index }}"><select name="service_catalogue_id" required><option value="">Choose platform service</option>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->name }} ({{ $service->service_code }})</option>@endforeach</select><input type="number" name="priority" min="1" max="65535" value="100" required><button class="btn secondary" type="submit">Create inactive mapping</button></form></td></tr>@endforeach
            </tbody></table></div>
        @elseif($catalogue['adapter']==='smeplug')
            <div class="table-wrap"><table><thead><tr><th>Supplier item</th><th>Supplier ID</th><th>Network ID</th><th>Map to platform service</th></tr></thead><tbody>
            @foreach($catalogue['items'] as $index=>$item)<tr><td>{{ $item['name'] ?? $item['plan_name'] ?? 'Supplier item' }}</td><td>{{ $item['id'] ?? $item['plan_id'] ?? '—' }}</td><td>{{ $item['network_id'] ?? ($catalogue['resource']==='networks' ? ($item['id'] ?? '—') : 'Not returned') }}</td><td><form method="post" action="{{ route('platform.integrations.providers.catalogue.map',$catalogue['provider_id']) }}">@csrf<input type="hidden" name="item_index" value="{{ $index }}"><select name="service_catalogue_id" required><option value="">Choose platform service</option>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->name }} ({{ $service->service_code }})</option>@endforeach</select><input type="number" name="priority" min="1" max="65535" value="100" required><button class="btn secondary" type="submit">Create inactive mapping</button></form></td></tr>@endforeach
            </tbody></table></div>
        @endif
        <details style="margin-top:12px"><summary>Inspect original supplier response</summary><pre style="white-space:pre-wrap;overflow:auto;max-height:420px">{{ json_encode($catalogue['payload'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre></details>
    </section>
    @endif
    <section class="card" style="margin:18px 0"><h3>Supplier connection maintenance</h3><p class="muted">Credentials are write-only: leave a credential blank to retain its currently encrypted value. Any update automatically deactivates the connection until it is rechecked.</p>
    @foreach($providers as $provider)
      <div style="border-top:1px solid #ddd;padding:14px 0"><strong>{{ $provider->name }}</strong> <span class="pill">{{ $provider->environment }} / {{ $provider->status }}</span>
        @if($provider->environment==='sandbox' && $provider->adapter==='smeplug')
          <form method="post" action="{{ route('platform.integrations.providers.catalogue',['provider'=>$provider->id,'resource'=>'networks']) }}" style="display:inline">@csrf<button class="btn secondary" type="submit">Fetch networks</button></form>
          <form method="post" action="{{ route('platform.integrations.providers.catalogue',['provider'=>$provider->id,'resource'=>'data-plans']) }}" style="display:inline">@csrf<button class="btn secondary" type="submit">Fetch data plans</button></form>
        @elseif($provider->environment==='sandbox' && $provider->adapter==='vtpass')
          <form method="post" action="{{ route('platform.integrations.providers.catalogue',['provider'=>$provider->id,'resource'=>'service-categories']) }}" style="display:inline">@csrf<button class="btn secondary" type="submit">Fetch VTpass categories</button></form>
        @endif
        <form method="post" action="{{ route('platform.integrations.providers.update',$provider->id) }}" style="margin-top:10px">@csrf @method('PUT')
          <div class="grid"><div class="field"><label>Display name</label><input name="name" value="{{ $provider->name }}" required></div>
          <div class="field"><label>Environment</label><select name="environment"><option value="sandbox" @selected($provider->environment==='sandbox')>Sandbox</option><option value="live" @selected($provider->environment==='live')>Live</option></select></div></div>
          <div class="grid"><div class="field"><label>API key (replace only)</label><input type="password" name="api_key" autocomplete="new-password"></div><div class="field"><label>Public key (replace only)</label><input type="password" name="public_key" autocomplete="new-password"></div><div class="field"><label>Secret key (replace only)</label><input type="password" name="secret_key" autocomplete="new-password"></div><div class="field"><label>Private key / API token (replace only)</label><input type="password" name="api_token" autocomplete="new-password"></div></div>
          <div class="field"><label>Merge supplier settings JSON</label><textarea name="settings_json" rows="3" placeholder="Leave blank to keep current settings"></textarea></div>
          <button class="btn secondary" type="submit">Update connection (deactivates)</button>
        </form>
        <small class="muted">Last read-only check: {{ $provider->last_health_check_at ?? 'Not checked' }}</small>
      </div>
    @endforeach
    </section>
    <section class="card" style="margin:18px 0"><h3>Maintain service definitions</h3>
    @foreach($services as $service)
      <form method="post" action="{{ route('platform.integrations.services.update',$service->id) }}" style="display:grid;grid-template-columns:1fr 2fr 2fr auto;gap:8px;align-items:end;margin:10px 0">@csrf @method('PUT')
        <div class="field"><label>{{ $service->service_code }}</label><input name="name" value="{{ $service->name }}" required></div>
        <div class="field"><label>Validation schema JSON</label><textarea name="validation_schema_json" rows="2" required>{{ json_encode($service->validation_schema) }}</textarea></div>
        <div class="muted">Current status: {{ $service->status }}. Saving returns the service to inactive.</div><button class="btn secondary">Save definition</button>
      </form>
    @endforeach
    </section>
    <section class="card" style="margin:18px 0"><h3>Maintain supplier product mappings</h3>
    @foreach($mappings as $mapping)
      <form method="post" action="{{ route('platform.integrations.mappings.update',$mapping->id) }}" style="display:grid;grid-template-columns:1fr 1fr 2fr auto;gap:8px;align-items:end;margin:10px 0">@csrf @method('PUT')
        <div class="muted">{{ $mapping->provider_name }} → {{ $mapping->service_name }}</div>
        <div class="field"><label>Supplier product ID/code</label><input name="provider_product_code" value="{{ $mapping->provider_product_code }}" required></div>
        <div class="field"><label>Mapping JSON</label><textarea name="mapping_json" rows="2">{{ json_encode($mapping->mapping) }}</textarea></div>
        <div><input type="number" name="priority" min="1" max="65535" value="{{ $mapping->priority }}" required><button class="btn secondary">Save mapping</button></div>
      </form>
    @endforeach
    </section>

</div>
@endsection
