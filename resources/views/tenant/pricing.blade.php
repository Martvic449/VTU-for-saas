@extends('layouts.app')
@section('title', $tenant->name.' · Pricing')
@section('content')
<div class="section-head"><span class="section-kicker">Revenue management</span><h1 class="title">Pricing & margins</h1><p class="section-intro">Review the active pricing rules assigned to your reseller business. Pricing changes are controlled by the platform team.</p></div>
<div class="table-wrap"><table><thead><tr><th>Service</th><th>Category</th><th>Cost</th><th>Sell price</th><th>Gross margin</th><th>Effective from</th></tr></thead><tbody>
@forelse($pricing as $rule)<tr><td><strong>{{ $rule->name }}</strong></td><td>{{ $rule->category }}</td><td>₦{{ number_format($rule->cost_minor / 100, 2) }}</td><td>₦{{ number_format($rule->sell_minor / 100, 2) }}</td><td>₦{{ number_format(($rule->sell_minor - $rule->cost_minor) / 100, 2) }}</td><td>{{ \Illuminate\Support\Carbon::parse($rule->effective_from)->format('d M Y') }}</td></tr>@empty<tr><td colspan="6">No active tenant-specific pricing rules have been assigned yet.</td></tr>@endforelse
</tbody></table></div><div style="margin-top:18px">{{ $pricing->links() }}</div>
@endsection