@extends('layouts.app')
@section('title','Email verified')
@section('content')
<div class="wrap">
  <div class="card form" style="text-align:center">
    <div style="width:58px;height:58px;border-radius:50%;background:#e7f7f0;color:#087451;display:grid;place-items:center;font-size:28px;margin:0 auto 16px">✓</div>
    <h1>Email address verified</h1>
    <p class="muted">Your email address has been verified successfully. You can now sign in to continue.</p>
    <a class="btn" href="{{ route('login') }}" style="width:100%;margin-top:12px">Continue to sign in</a>
  </div>
</div>
@endsection
