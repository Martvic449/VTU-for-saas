@extends('layouts.app')
@section('title', 'Choose your account type')
@section('content')
<div class="wrap" style="max-width:1050px">
  <section class="form" style="max-width:760px;margin:42px auto 24px;text-align:center">
    <span class="pill">Welcome to {{ config('app.name', 'VTU SaaS') }}</span>
    <h1 style="font-size:clamp(28px,4vw,38px);margin:14px 0 8px">Choose your account type</h1>
    <p class="muted">Select the option that best describes how you want to use the platform. You can sign in to an existing account at any time.</p>
  </section>
  <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr));max-width:850px;margin:24px auto">
    <article class="card" style="padding:28px">
      <span class="pill">For personal use</span>
      <h2 style="margin:14px 0 8px">Customer account</h2>
      <p class="muted">I want to buy airtime, data and other available digital services for myself.</p>
      <ul class="muted" style="padding-left:19px"><li>Personal account and dashboard</li><li>Wallet and purchase history</li><li>Access to services enabled on the platform</li></ul>
      <a class="btn" style="width:100%;margin-top:12px" href="{{ route('register') }}">Create customer account</a>
    </article>
    <article class="card" style="padding:28px">
      <span class="pill">For entrepreneurs and businesses</span>
      <h2 style="margin:14px 0 8px">Reseller account</h2>
      <p class="muted">I want to sell digital services to my own customers and operate a VTU business.</p>
      <ul class="muted" style="padding-left:19px"><li>Dedicated business workspace</li><li>Customer and transaction management</li><li>Path to developer API access</li></ul>
      <a class="btn" style="width:100%;margin-top:12px" href="{{ route('register.reseller') }}">Create reseller account</a>
    </article>
  </div>
  <p class="muted" style="text-align:center;margin:24px 0 48px">Already have an account? <a href="{{ route('login') }}" style="color:var(--green-dark);font-weight:700">Sign in here</a></p>
</div>
@endsection
