@extends('layouts.app')
@section('title', 'Choose your account type')
@section('content')
<div class="home-section account-choice-page" style="min-height:70vh">
  <div class="section-inner account-choice-inner" style="max-width:960px">
    <div class="account-choice-intro" style="max-width:690px;text-align:center;margin:8px auto 30px">
      <span class="section-kicker">A good place to begin</span>
      <h1 class="title" style="font-size:clamp(30px,4vw,42px);margin-top:10px">Which account is right for you?</h1>
      <p class="section-intro" style="margin:0 auto">Choose how you plan to use the platform. This helps us set up the right account and workspace from the start.</p>
    </div>
    <div class="account-type-grid" style="max-width:850px;margin:0 auto">
      <article class="card" style="padding:clamp(22px,4vw,34px)">
        <div class="feature-icon" style="width:43px;height:43px;font-size:19px">◎</div>
        <span class="pill">Personal use</span>
        <h2 style="font-size:23px;margin:12px 0 7px">Customer account</h2>
        <p class="muted">I want to buy airtime, data and other available digital services for myself.</p>
        <ul class="muted" style="padding-left:19px;line-height:2;margin:18px 0 24px"><li>Personal customer dashboard</li><li>Wallet and purchase history</li><li>Services enabled for customers</li></ul>
        <a class="btn" style="width:100%" href="{{ route('register') }}">Create customer account <span aria-hidden="true">→</span></a>
      </article>
      <article class="card" style="padding:clamp(22px,4vw,34px);border-color:#cfe8dc">
        <div class="feature-icon" style="width:43px;height:43px;font-size:19px;background:#e7f5ee">▥</div>
        <span class="pill" style="background:#e7f5ee;color:#087456">Business use</span>
        <h2 style="font-size:23px;margin:12px 0 7px">Reseller account</h2>
        <p class="muted">I want to sell digital services to my own customers and operate a VTU business.</p>
        <ul class="muted" style="padding-left:19px;line-height:2;margin:18px 0 24px"><li>Dedicated reseller workspace</li><li>Customer and transaction management</li><li>Pathway to developer API access</li></ul>
        <a class="btn" style="width:100%" href="{{ route('register.reseller') }}">Apply for reseller account <span aria-hidden="true">→</span></a>
      </article>
    </div>
    <p class="muted" style="text-align:center;margin:25px 0 0">Already registered? <a href="{{ route('login') }}" style="color:var(--green-dark);font-weight:700">Sign in to your account</a></p>
  </div>
</div>
@endsection
