@extends('layouts.app')
@section('title','Reports & exports')
@section('content')
<div class="section-head"><h1 class="title">Reports & exports</h1><p>Operational counts from the live database. Download transaction records for reconciliation in your accounting workflow.</p></div>
<div class="grid">@foreach($summary as $label=>$value)<div class="card"><div class="stat-label">{{ str_replace('_',' ',ucfirst($label)) }}</div><div class="metric">@if($label==='gross_margin_minor')₦{{ number_format($value/100,2) }}@else{{ number_format($value) }}@endif</div></div>@endforeach</div>
<div class="card"><h3 style="margin-top:0">Transaction export</h3><p class="muted">CSV includes reference, tenant, wallet, service, state, amount/cost in minor units, currency and provider reference. It does not include customer input payloads or credentials.</p><form method="get" action="{{ route('platform.reports.transactions') }}" class="row" style="justify-content:flex-start"><div class="field" style="margin:0"><label>From date</label><input type="date" name="from"></div><div class="field" style="margin:0"><label>To date</label><input type="date" name="to"></div><button class="btn" style="align-self:end">Export transactions CSV</button></form></div>
@endsection
