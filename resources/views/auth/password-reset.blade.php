@extends('layouts.app')
@section('title','Choose a new password')
@section('content')
<div class="wrap"><div class="card form">
  <h1>Choose a new password</h1><p class="muted">Use at least 12 characters. Resetting your password will sign out existing API tokens.</p>
  @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
  <form method="post" action="{{ route('password.update') }}">@csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="field"><label for="reset-email">Email address</label><input id="reset-email" type="email" name="email" value="{{ $email }}" required autocomplete="email"></div>
    <div class="field"><label for="reset-password">New password</label><div class="password-control"><input id="reset-password" type="password" name="password" minlength="12" required autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle="reset-password" aria-controls="reset-password" aria-pressed="false">Show</button></div></div>
    <div class="field"><label for="reset-password-confirmation">Confirm new password</label><div class="password-control"><input id="reset-password-confirmation" type="password" name="password_confirmation" minlength="12" required autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle="reset-password-confirmation" aria-controls="reset-password-confirmation" aria-pressed="false">Show</button></div></div>
    <button class="btn" style="width:100%">Update password</button>
  </form>
</div></div>
@endsection
