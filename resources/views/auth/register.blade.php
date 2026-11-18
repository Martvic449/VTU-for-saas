@extends('layouts.app')
@section('title','Create customer account')
@section('content')
<div class="wrap">
  <div class="card form">
    <span class="pill">Personal account</span>
    <h1>Create your customer account</h1>
    <p class="muted">Get started with your digital services wallet.</p>
    @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('register') }}">@csrf
      <div class="field"><label for="customer-name">Full name</label><input id="customer-name" name="name" value="{{ old('name') }}" required autocomplete="name"></div>
      <div class="field"><label for="customer-email">Email address</label><input id="customer-email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></div>
      <div class="field"><label for="customer-password">Password (12+ characters)</label><div class="password-control"><input id="customer-password" name="password" type="password" required minlength="12" autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle="customer-password" aria-controls="customer-password" aria-pressed="false">Show</button></div></div>
      <div class="field"><label for="customer-password-confirmation">Confirm password</label><div class="password-control"><input id="customer-password-confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle="customer-password-confirmation" aria-controls="customer-password-confirmation" aria-pressed="false">Show</button></div></div>
      <button class="btn" type="submit">Create customer account</button>
    </form>
    <p class="muted">Want to run a VTU business? <a href="{{ route('register.reseller') }}" style="color:var(--green);font-weight:700">Create a reseller account</a><br>Already registered? <a href="{{ route('login') }}" style="color:var(--green);font-weight:700">Sign in</a></p>
  </div>
</div>
@endsection
