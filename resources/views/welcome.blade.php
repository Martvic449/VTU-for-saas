@extends('layouts.app')
@section('title', 'Buy digital services or build your VTU business')
@section('content')
<div class="wrap">
  <section class="hero" style="max-width:980px;padding:76px 0 54px">
    <span class="pill">Airtime · Data · Digital services · Reseller tools</span>
    <h1>Everything digital. One simpler way to buy, sell and grow.</h1>
    <p>Top up for yourself in a few steps, or build a digital services business with a dedicated reseller workspace. Choose the account that fits the way you want to use VTU SaaS.</p>
    <div class="row" style="justify-content:flex-start;margin-top:28px">
      <a class="btn" href="{{ route('account.type') }}">Choose your account <span aria-hidden="true">→</span></a>
      <a class="btn secondary" href="{{ route('login') }}">Sign in</a>
    </div>
    <p style="font-size:12px;margin-top:18px">Services, networks, prices and fulfilment depend on active supplier connections, availability and account eligibility.</p>
  </section>

  <section style="margin:0 0 62px">
    <div class="section-head"><h2 class="title">What would you like to do?</h2><p>Start with the account made for your needs. You can use one sign-in page after registration.</p></div>
    <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:20px">
      <article class="card" style="padding:clamp(22px,3vw,34px)">
        <span class="pill">Personal use</span>
        <h3 style="font-size:23px;margin:16px 0 8px">I want to buy for myself</h3>
        <p class="muted">Create a customer account to purchase airtime, data and other available digital services, then keep track of your wallet activity and purchase history.</p>
        <ul class="muted" style="padding-left:19px;margin:18px 0"><li>Personal customer dashboard</li><li>Wallet and purchase history</li><li>Simple access to available services</li></ul>
        <a class="btn" href="{{ route('register') }}">Create customer account</a>
      </article>
      <article class="card" style="padding:clamp(22px,3vw,34px);border-color:#b9e6d8">
        <span class="pill" style="background:#e7f7f0;color:#087451">Business use</span>
        <h3 style="font-size:23px;margin:16px 0 8px">I want to sell to customers</h3>
        <p class="muted">Apply for a reseller workspace to run your own VTU business, manage customers and transactions, and build your digital services brand.</p>
        <ul class="muted" style="padding-left:19px;margin:18px 0"><li>Dedicated business workspace</li><li>Customer and transaction management</li><li>Pathway to API and developer tools</li></ul>
        <a class="btn" href="{{ route('register.reseller') }}">Apply for reseller account</a>
      </article>
    </div>
  </section>

  <section class="card" style="padding:clamp(22px,4vw,42px);margin-bottom:54px">
    <div class="section-head"><h2 class="title">Built to support the next step</h2><p>Keep everyday purchases and business operations organised as your needs grow.</p></div>
    <div class="grid" style="grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:0">
      <div><strong>For customers</strong><p class="muted">A personal account for supported purchases, wallet activity and receipts.</p></div>
      <div><strong>For resellers</strong><p class="muted">A separate business workspace for your customers, services and transactions.</p></div>
      <div><strong>For developers</strong><p class="muted">API credentials and webhook tools are managed through an approved reseller workspace.</p></div>
    </div>
  </section>

  <section style="max-width:760px;margin:0 auto 58px;text-align:center">
    <h2 class="title">Already registered?</h2>
    <p class="muted">Customers and resellers use the same secure sign-in page. We’ll direct you to the workspace associated with your account.</p>
    <a class="btn secondary" href="{{ route('login') }}">Sign in to your account</a>
  </section>
</div>
@endsection
