@extends('layouts.app')
@section('title', 'A simpler way to buy and resell digital services')
@section('content')
<div class="wrap">
  <section class="hero" style="max-width:900px;padding-top:76px">
    <span class="pill">VTU services · Reseller tools · Business API</span>
    <h1>Digital services, made simple to buy and easier to sell.</h1>
    <p>Buy airtime, data and other supported digital services for yourself, or build a reseller business with your own customer workspace. One platform, with clear tools for every kind of user.</p>
    <div class="row" style="justify-content:flex-start;margin-top:26px">
      <a class="btn" href="{{ route('account.type') }}">Create an account</a>
      <a class="btn secondary" href="{{ route('login') }}">Sign in</a>
    </div>
    <p style="font-size:12px;margin-top:18px">Available services, networks, prices and fulfilment depend on active supplier connections and account eligibility.</p>
  </section>

  <section style="margin:8px 0 58px">
    <div class="section-head"><h2 class="title">Choose how you want to use the platform</h2><p>Start with the account that matches what you want to do.</p></div>
    <div class="grid">
      <article class="card"><div class="pill">For personal use</div><h3>Customer account</h3><p class="muted">Buy airtime, data and other available digital services for yourself. Manage your profile, wallet activity and purchase history in one place.</p><a href="{{ route('register') }}" style="color:var(--green-dark);font-weight:700">Create customer account →</a></article>
      <article class="card"><div class="pill">For entrepreneurs</div><h3>Reseller account</h3><p class="muted">Start a digital services business, serve your own customers and manage your storefront, business wallet and transactions from a dedicated workspace.</p><a href="{{ route('register.reseller') }}" style="color:var(--green-dark);font-weight:700">Create reseller account →</a></article>
      <article class="card"><div class="pill">For developers & businesses</div><h3>API access</h3><p class="muted">Connect your application to supported services using scoped API credentials, transaction references and signed webhooks. API access is managed through an approved business workspace.</p><a href="{{ route('register.reseller') }}" style="color:var(--green-dark);font-weight:700">Start with business access →</a></article>
    </div>
  </section>

  <section class="card" style="padding:clamp(22px,4vw,42px);margin-bottom:48px">
    <h2 class="title">A clear workspace as you grow</h2>
    <p class="muted" style="max-width:850px">Keep purchases, customer activity and business operations organised. Reseller workspaces are separated from one another, while platform services and integrations are enabled only after the required configuration and checks.</p>
    <div class="grid" style="margin-bottom:0">
      <div><strong>Customer tools</strong><p class="muted">Account, wallet activity and purchase history.</p></div>
      <div><strong>Reseller tools</strong><p class="muted">Business workspace, customer management and service pricing.</p></div>
      <div><strong>Developer tools</strong><p class="muted">API credentials, usage visibility and webhook management.</p></div>
    </div>
  </section>

  <section style="max-width:760px;margin:0 auto 55px;text-align:center">
    <h2 class="title">Already have an account?</h2><p class="muted">Use the same secure sign-in page for customer and reseller accounts. We’ll direct you to the workspace associated with your account.</p>
    <a class="btn" href="{{ route('login') }}">Sign in to your account</a>
  </section>
</div>
@endsection
