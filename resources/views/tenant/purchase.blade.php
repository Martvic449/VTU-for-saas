@extends('layouts.app')
@section('title', $tenant->name.' · Buy services')
@section('content')
<div class="section-head"><span class="section-kicker">Reseller sales</span><h1 class="title">Buy services</h1><p class="section-intro">Submit a service request using your reseller wallet. Every purchase is validated, priced and reserved by the platform before supplier submission.</p></div>
@if($errors->has('purchase'))<div class="alert" style="background:#fff0ef;color:#a52b24">{{ $errors->first('purchase') }}</div>@endif
@if($visible->isEmpty())<div class="card"><h3>No purchasable services are available</h3><p class="muted">This can happen when provider mappings, pricing rules or service validation fields have not been configured for the current execution environment.</p><a class="btn secondary" href="{{ route('tenant.services') }}">View service catalogue</a></div>@endif
@foreach($visible as $service)
<div class="card" style="margin:16px 0">
 <div class="row"><div><span class="section-kicker">{{ $service->category }}</span><h2 style="margin:4px 0">{{ $service->name }}</h2><p class="muted" style="margin:0">Service code: {{ $service->service_code }}</p></div><div style="text-align:right"><div class="stat-label">Current sell price</div><strong style="font-size:20px">₦{{ number_format($service->sell_minor / 100, 2) }}</strong></div></div>
 <form method="post" action="{{ route('tenant.purchase.store') }}" style="margin-top:15px">@csrf
  <input type="hidden" name="service_code" value="{{ $service->service_code }}"><input type="hidden" name="idempotency_key" value="{{ $service->idempotency_key }}">
  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr));margin:10px 0">
   @foreach($service->validation_schema as $field => $rules)
    @php($ruleText=is_array($rules)?implode('|',$rules):(string)$rules)
    <div class="field" style="margin:0"><label for="service-{{ $service->id }}-{{ $field }}">{{ ucwords(str_replace(['_','-'],' ',$field)) }}</label><input id="service-{{ $service->id }}-{{ $field }}" name="customer[{{ $field }}]" value="{{ old('customer.'.$field) }}" @if(str_contains($ruleText,'required')) required @endif placeholder="Enter {{ strtolower(str_replace('_',' ',$field)) }}"></div>
   @endforeach
  </div>
  <button class="btn" type="submit" onclick="return confirm('Submit this purchase using your reseller wallet?')">Submit purchase</button>
 </form>
</div>
@endforeach
<p class="muted" style="font-size:12px">Supplier responses may be pending. Do not resubmit a pending transaction; use the transaction reference to monitor its status.</p>
@endsection