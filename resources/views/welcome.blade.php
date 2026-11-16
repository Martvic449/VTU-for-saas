@extends('layouts.app')
@section('title', 'VTU, reseller and developer infrastructure')
@section('content')
<div class="wrap">
  <section class="hero" style="max-width:900px;padding-top:82px">
    <span class="pill">Digital services · Reseller platform · Developer API</span>
    <h1>Power your digital services business from one platform.</h1>
    <p>Launch a VTU and digital-services storefront, manage customer activity, and connect your business through a versioned API. Whether you are buying services, building a reseller business or integrating at scale, the platform gives you one place to manage the workflow.</p>
    <div class="row" style="justify-content:flex-start;margin-top:26px">
      <a class="btn" href="{{ route('register') }}">Get started</a>
      <a class="btn secondary" href="{{ route('register.reseller') }}">Start a reseller business</a>
      <a class="btn secondary" href="{{ route('login') }}">Sign in</a>
    </div>
    <p style="font-size:12px;margin-top:18px">Service availability, supported networks, prices and fulfilment times depend on active supplier connections and your account or reseller plan.</p>
  </section>

  <section style="margin:10px 0 54px">
    <div class="section-head"><h2 class="title">One platform. Three ways to grow.</h2><p>Choose the way you want to use the infrastructure.</p></div>
    <div class="grid">
      <article class="card"><div class="pill">For individuals</div><h3>Buy digital services</h3><p class="muted">Create an account, fund your eligible prepaid service wallet and purchase available digital services from your dashboard.</p><a href="{{ route('register') }}" style="color:var(--green-dark);font-weight:700">Create customer account →</a></article>
      <article class="card"><div class="pill">For entrepreneurs</div><h3>Run a reseller storefront</h3><p class="muted">Set up a tenant business, serve your own customers and manage your business activity from a dedicated workspace.</p><a href="{{ route('register.reseller') }}" style="color:var(--green-dark);font-weight:700">Become a reseller →</a></article>
      <article class="card"><div class="pill">For builders</div><h3>Integrate with our API</h3><p class="muted">Build connected customer experiences using scoped API credentials, transaction references and signed event webhooks.</p><a href="{{ route('register.reseller') }}" style="color:var(--green-dark);font-weight:700">Explore business access →</a></article>
    </div>
  </section>

  <section class="card" style="padding:clamp(22px,4vw,42px);margin-bottom:48px">
    <div class="grid" style="margin:0;align-items:center">
      <div style="grid-column:span 2"><h2 class="title">Built around visibility and control.</h2><p class="muted">Track transaction states, review account activity and use a structured workspace for customers, resellers and platform operations. Financial records are designed around a ledger rather than editable wallet totals.</p></div>
      <div><div style="font-weight:800;font-family:Manrope;font-size:18px">Designed for connected businesses</div><p class="muted" style="font-size:13px">Tenant-aware workspaces · API access controls · Transaction history · Operational review</p></div>
    </div>
  </section>

  <section style="max-width:760px;margin:0 auto 55px;text-align:center">
    <h2 class="title">Ready to get started?</h2><p class="muted">Create an account to explore the available services and business options.</p>
    <a class="btn" href="{{ route('register') }}">Create your account</a>
  </section>
</div>
@endsection
