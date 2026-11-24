@extends('layouts.app')
@section('title','Application & account')
@section('content')
<div class="section-head">
  <span class="section-kicker">Account management</span>
  <h1 class="title">Application & account</h1>
  <p class="section-intro">Review your reseller application, assigned business address and subscription status.</p>
</div>
@if($applications->isEmpty())
  <div class="card"><h3>No reseller application found</h3><p class="muted">There is no reseller business linked to this account. Contact support if you believe this is an error.</p><a class="btn" href="{{ route('support.contact') }}">Contact support</a></div>
@else
  @foreach($applications as $application)
    @php($subscription = $subscriptions->get($application->id))
    <div class="card" style="margin-bottom:18px">
      <div class="row"><div><span class="section-kicker">Business application</span><h2 style="margin:4px 0">{{ $application->name }}</h2><p class="muted" style="margin:0">Reference: {{ $application->slug }}</p></div><span class="pill {{ $application->status }}">{{ ucfirst($application->status) }}</span></div>
      <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
        <div><div class="stat-label">Owner email</div><strong>{{ auth()->user()->hasVerifiedEmail() ? 'Verified' : 'Not verified' }}</strong></div>
        <div><div class="stat-label">Application</div><strong>{{ ucfirst($application->status) }}</strong></div>
        <div><div class="stat-label">Assigned address</div><strong style="overflow-wrap:anywhere">{{ $application->hostname ?: 'Not assigned' }}</strong></div>
        <div><div class="stat-label">Domain status</div><strong>{{ $application->verified_at ? 'Platform verified' : 'Pending' }}</strong></div>
        <div><div class="stat-label">HTTPS status</div><strong>{{ ucfirst($application->tls_status ?? 'pending') }}</strong></div>
        <div><div class="stat-label">Subscription</div><strong>{{ $subscription?->plan_name ?? 'Not assigned' }}</strong></div>
      </div>
      <p class="muted">Platform-managed subdomains are verified through the platform's wildcard DNS configuration. Custom domains you own use a separate verification process.</p>
    </div>
  @endforeach
@endif
<div class="card">
  <h3 style="margin-top:0">Account owner</h3>
  <p><strong>{{ auth()->user()->name }}</strong></p>
  <p class="muted">{{ auth()->user()->email }} · {{ auth()->user()->hasVerifiedEmail() ? 'Email verified' : 'Email verification pending' }}</p>
  <a class="btn secondary" href="{{ route('profile.edit') }}">Manage email settings</a>
  <a class="btn secondary" href="{{ route('support.contact') }}">Contact support</a>
</div>
@endsection
