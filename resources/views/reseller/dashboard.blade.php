@extends('layouts.app')
@section('title','Reseller workspace')
@section('content')
<div class="section-head">
  <span class="section-kicker">Business workspace</span>
  <h1 class="title">Welcome, {{ auth()->user()->name }}</h1>
  <p class="section-intro">Track your reseller application, business approval and domain activation from one place.</p>
</div>

@if($applications->isEmpty())
  <div class="card">
    <h2>No reseller application found</h2>
    <p class="muted">Your account has the reseller-owner role, but no business application is linked to it. Contact platform support for assistance.</p>
    <a class="btn secondary" href="{{ route('support.contact') }}">Contact support</a>
  </div>
@else
  @foreach($applications as $application)
    @php
      $domainReady = $application->status === 'active'
        && $application->verified_at
        && $application->tls_status === 'active'
        && $application->hostname;
    @endphp
    <div class="card" style="margin-bottom:20px">
      <div class="row" style="align-items:flex-start">
        <div>
          <span class="section-kicker">Reseller application</span>
          <h2 style="margin:5px 0">{{ $application->name }}</h2>
          <p class="muted" style="margin:0">Business reference: {{ $application->slug }}</p>
        </div>
        <span class="pill {{ $application->status }}">{{ ucfirst($application->status) }}</span>
      </div>
      <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr));margin:20px 0">
        <div><div class="stat-label">Owner email</div><strong>{{ auth()->user()->hasVerifiedEmail() ? 'Verified' : 'Verification required' }}</strong></div>
        <div><div class="stat-label">Application approval</div><strong>{{ ucfirst($application->status) }}</strong></div>
        <div><div class="stat-label">Domain verification</div><strong>{{ $application->verified_at ? 'Verified' : 'Pending' }}</strong></div>
        <div><div class="stat-label">TLS / HTTPS</div><strong>{{ ucfirst(str_replace('_',' ', $application->tls_status ?? 'pending')) }}</strong></div>
      </div>
      @if($domainReady)
        <div class="alert">Your business workspace is ready.</div>
        <a class="btn" href="https://{{ $application->hostname }}/tenant/dashboard">Open business dashboard</a>
      @else
        <div class="alert" style="background:#fff4dc;color:#805400">
          @if($application->status !== 'active')
            Your application is awaiting platform approval. Business tools remain locked until approval, domain verification and HTTPS activation are complete.
          @elseif(!$application->verified_at)
            Your application is approved. The business domain {{ $application->hostname ?: 'has not been assigned' }} still needs DNS ownership verification.
          @elseif($application->tls_status !== 'active')
            Your application and domain are verified. HTTPS activation for {{ $application->hostname }} is still pending.
          @else
            Your business workspace is being prepared.
          @endif
        </div>
        @if(!auth()->user()->hasVerifiedEmail())
          <form method="post" action="{{ route('verification.send') }}">@csrf<button class="btn" type="submit">Resend verification email</button></form>
        @endif
        @if($application->hostname)
          <p class="muted" style="font-size:13px;margin-bottom:0">Assigned business address: <strong>{{ $application->hostname }}</strong>. The DNS record and a valid HTTPS certificate must be configured before this workspace can open.</p>
        @endif
      @endif
    </div>
  @endforeach
@endif

<div class="card">
  <h3 style="margin-top:0">Onboarding checklist</h3>
  <ol style="padding-left:20px;color:var(--muted);line-height:1.9">
    <li>{{ auth()->user()->hasVerifiedEmail() ? '✓ Owner email verified' : 'Verify the owner email address' }}</li>
    <li>{{ $applications->contains(fn ($item) => $item->status === 'active') ? '✓ Reseller application approved' : 'Awaiting platform approval' }}</li>
    <li>{{ $applications->contains(fn ($item) => (bool) $item->verified_at) ? '✓ Business domain verified' : 'Awaiting DNS ownership verification' }}</li>
    <li>{{ $applications->contains(fn ($item) => $item->tls_status === 'active') ? '✓ HTTPS certificate active' : 'Awaiting valid HTTPS activation' }}</li>
  </ol>
  <a href="{{ route('support.contact') }}" style="color:var(--green-dark);font-weight:700">Need help? Contact support</a>
</div>
@endsection