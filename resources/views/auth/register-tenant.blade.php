@extends('layouts.app')
@section('title','Apply for a reseller account')
@section('content')
<div class="wrap">
  <div class="card form" style="max-width:540px">
    <span class="pill">Business account</span>
    <h1>Apply for a reseller account</h1>
    <p class="muted">Create your business profile and request a dedicated VTU workspace. Your tenant remains pending until it has been reviewed and its domain activated.</p>
    @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('register.reseller.store') }}">@csrf
      <div class="field"><label for="business-name">Business name</label><input id="business-name" name="business_name" value="{{ old('business_name') }}" required maxlength="160" autocomplete="organization"></div>
      <div class="field"><label for="business-slug">Preferred subdomain</label><div class="row" style="gap:6px;flex-wrap:nowrap"><input id="business-slug" name="slug" value="{{ old('slug') }}" required minlength="3" maxlength="50" pattern="[A-Za-z0-9_-]+"><span class="muted">.{{ config('tenancy.base_domain','yourdomain.com') }}</span></div></div>
      <div class="field"><label for="owner-name">Owner full name</label><input id="owner-name" name="owner_name" value="{{ old('owner_name') }}" required maxlength="160" autocomplete="name"></div>
      <div class="field"><label for="reseller-email">Business email</label><input id="reseller-email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></div>
      <div class="field"><label for="reseller-phone">Phone number (optional)</label><input id="reseller-phone" name="phone" value="{{ old('phone') }}" autocomplete="tel"></div>
      <div class="field"><label for="reseller-password">Password (12+ characters)</label><div class="password-control"><input id="reseller-password" name="password" type="password" required minlength="12" autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle="reseller-password" aria-controls="reseller-password" aria-pressed="false">Show</button></div></div>
      <div class="field"><label for="reseller-password-confirmation">Confirm password</label><div class="password-control"><input id="reseller-password-confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle="reseller-password-confirmation" aria-controls="reseller-password-confirmation" aria-pressed="false">Show</button></div></div>
      <button class="btn" type="submit">Submit reseller application</button>
    </form>
    <p class="muted">Already have an account? <a href="{{ route('login') }}" style="color:var(--green);font-weight:700">Sign in</a></p>
  </div>
</div>
@endsection
