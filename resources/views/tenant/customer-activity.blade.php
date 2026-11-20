@extends('layouts.app')
@section('title','Transaction history')
@section('content')
<div class="section-head">
  <h1 class="title">Transaction history</h1>
  <p>Review your wallet deposits and digital service purchases.</p>
</div>
<div class="card" style="margin-bottom:20px">
  <h3 style="margin-top:0">Recent deposits</h3>
  <div class="table-wrap"><table><thead><tr><th>Reference</th><th>Gateway</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>
  @forelse($deposits as $deposit)
    <tr><td>{{ $deposit->reference }}</td><td>{{ strtoupper($deposit->gateway) }}</td><td>₦{{ number_format($deposit->amount_minor/100,2) }}</td><td><span class="pill {{ $deposit->status }}">{{ $deposit->status }}</span></td><td>{{ \\Illuminate\\Support\\Carbon::parse($deposit->created_at)->format('d M Y H:i') }}</td></tr>
  @empty
    <tr><td colspan="5" class="empty">No deposits yet.</td></tr>
  @endforelse
  </tbody></table></div>
  @if(method_exists($deposits, 'links'))<div style="margin-top:14px">{{ $deposits->links() }}</div>@endif
</div>
<div class="card">
  <h3 style="margin-top:0">Recent transactions</h3>
  <div class="table-wrap"><table><thead><tr><th>Reference</th><th>Service</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>
  @forelse($transactions as $tx)
    <tr><td>{{ $tx->reference }}</td><td>#{{ $tx->service_catalogue_id }}</td><td>₦{{ number_format($tx->sell_minor/100,2) }}</td><td><span class="pill {{ $tx->status }}">{{ $tx->status }}</span></td><td>{{ \\Illuminate\\Support\\Carbon::parse($tx->created_at)->format('d M Y H:i') }}</td></tr>
  @empty
    <tr><td colspan="5" class="empty">No purchases yet.</td></tr>
  @endforelse
  </tbody></table></div>
  @if(method_exists($transactions, 'links'))<div style="margin-top:14px">{{ $transactions->links() }}</div>@endif
</div>
@endsection
