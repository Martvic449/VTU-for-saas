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
        <div><div class="stat-label">Application approval</div><strong>{{ ucfirst($application->status) }}</strong></div>
        <div><div class="stat-label">Domain verification</div><strong>{{ $application->verified_at ? 'Verified' : 'Pending' }}</strong></div>
        <div><div class="stat-label">TLS / HTTPS</div><strong>{{ ucfirst(str_replace('_',' ', $application->tls_status ?? 'pending')) }}</strong></div>
      </div>
      @if($domainReady)
        <div class="alert">Your business workspace is ready.</div>
        <a class="btn" href="https://{{ $application->hostname }}/tenant/dashboard">Open business dashboard</a>
      @else
        <div class="alert" style="background:#fff4dc;color:#805400">
          Your reseller workspace is not active yet. Platform approval, domain verification and HTTPS activation must all be completed before business tools become available.
        </div>
        <p class="muted" style="font-size:13px;margin-bottom:0">You can sign in and check this page while your application is being reviewed. You will not be able to make reseller purchases or use the business API until activation is complete.</p>
      @endif
    </div>
  @endforeach
@endif

<div class="card">
  <h3 style="margin-top:0">What happens next?</h3>
  <ol style="padding-left:20px;color:var(--muted);line-height:1.9">
    <li>Verify your business email address.</li>
    <li>The platform team reviews and approves your reseller application.</li>
    <li>Your business subdomain is verified and HTTPS is activated.</li>
    <li>Your reseller dashboard and enabled business tools become available.</li>
  </ol>
  <a href="{{ route('support.contact') }}" style="color:var(--green-dark);font-weight:700">Need help? Contact support</a>
</div>
@endsection
