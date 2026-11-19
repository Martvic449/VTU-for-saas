@extends('layouts.app')
@section('title','Sign in')
@section('content')
<div class="wrap">
  <div class="card form">
    <span class="pill">Secure account access</span>
    <h1>Welcome back</h1>
    <p class="muted">Customers and resellers use the same secure sign-in. We’ll take you to the workspace available to your account.</p>
    @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('login') }}">@csrf
      <div class="field"><label for="login-email">Email address</label><input id="login-email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></div>
      <div class="field"><label for="login-password">Password</label><div class="password-control"><input id="login-password" name="password" type="password" required autocomplete="current-password"><button class="password-toggle" type="button" data-password-toggle="login-password" aria-controls="login-password" aria-pressed="false">Show</button></div></div>
      <button class="btn" type="submit" style="width:100%">Sign in</button>
    </form>
    <p class="muted"><a href="{{ route('password.request') }}" style="color:var(--green);font-weight:700">Forgot password?</a></p>
    <p class="muted">New here? <a href="{{ route('account.type') }}" style="color:var(--green);font-weight:700">Choose your account type</a></p>
  </div>
</div>
@endsection
