@extends('layouts.app')
@section('title', $tenant->name.' · Service catalogue')
@section('content')
<div class="section-head"><span class="section-kicker">Products & services</span><h1 class="title">Service catalogue</h1><p class="section-intro">Browse the services currently enabled on the platform and check whether tenant-specific pricing has been configured.</p></div>
<div class="table-wrap"><table><thead><tr><th>Service</th><th>Category</th><th>Code</th><th>Reseller price</th><th>Estimated margin</th><th>Availability</th></tr></thead><tbody>
@forelse($services as $service)<tr><td><strong>{{ $service->name }}</strong></td><td>{{ $service->category }}</td><td>{{ $service->service_code }}</td><td>{{ $service->reseller_price_minor !== null ? '₦'.number_format($service->reseller_price_minor / 100, 2) : 'Not configured' }}</td><td>{{ $service->estimated_margin_minor !== null ? '₦'.number_format($service->estimated_margin_minor / 100, 2) : '—' }}</td><td><span class="pill {{ $service->reseller_price_minor !== null ? 'active' : 'pending' }}">{{ $service->reseller_price_minor !== null ? 'Priced' : 'Awaiting pricing' }}</span></td></tr>@empty<tr><td colspan="6">No active services are currently listed.</td></tr>@endforelse
</tbody></table></div>
<p class="muted" style="margin-top:14px;font-size:12px">Prices and margins are informational. Purchases remain subject to tenant subscription entitlements, wallet balance, provider availability and sandbox/live configuration.</p>
@endsection