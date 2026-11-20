@extends('layouts.app')
@push('head')
<style>
.support-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.2fr);gap:20px;align-items:start}
.support-layout>.card{min-width:0}
.support-ticket{padding:13px 0;border-bottom:1px solid var(--line);min-width:0}
.support-ticket-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;min-width:0}
.support-ticket-heading strong{min-width:0;overflow-wrap:anywhere}
.support-ticket-meta{font-size:12px;overflow-wrap:anywhere}
.support-layout input,.support-layout select,.support-layout textarea{max-width:100%;min-width:0}
.support-layout textarea{width:100%;resize:vertical}
.support-pagination{max-width:100%;overflow-x:auto}
@media(max-width:760px){
 .support-layout{grid-template-columns:minmax(0,1fr);gap:14px}
 .support-layout>.card{padding:18px}
 .support-layout .btn{width:100%;white-space:normal}
 .support-ticket-heading{flex-wrap:wrap}
 .support-pagination{padding-bottom:4px}
}
</style>
@endpush
@section('title','Help & support')
@section('content')
<div class="section-head"><h1 class="title">Help & support</h1><p>Send a support request and track its status. Never include passwords, API secrets, full card details or identity numbers in a ticket.</p></div>
<div class="support-layout">
  <div class="card">
    <h3 style="margin-top:0">Create support request</h3>
    <form method="post" action="{{ route('support.create') }}">@csrf
      <div class="field"><label>Subject</label><input name="subject" required maxlength="180"></div>
      <div class="field"><label>Priority</label><select name="priority"><option value="normal">Normal</option><option value="low">Low</option><option value="high">High</option></select></div>
      <div class="field"><label>Describe the issue</label><textarea name="description" rows="6" minlength="20" maxlength="5000" required></textarea></div>
      <button class="btn" type="submit">Submit request</button>
    </form>
  </div>
  <div class="card">
    <h3 style="margin-top:0">Your requests</h3>
    @forelse($tickets as $ticket)
      <div class="support-ticket">
        <div class="support-ticket-heading"><strong>{{ $ticket->subject }}</strong><span class="pill {{ $ticket->status }}">{{ str_replace('_',' ',$ticket->status) }}</span></div>
        <div class="muted support-ticket-meta">{{ $ticket->reference }} · {{ \Illuminate\Support\Carbon::parse($ticket->created_at)->format('d M Y') }} · {{ ucfirst($ticket->priority) }} priority</div>
      </div>
    @empty
      <div class="empty">You have not opened a support request yet.</div>
    @endforelse
    <div class="support-pagination" style="margin-top:14px">{{ $tickets->links() }}</div>
  </div>
</div>
@endsection
