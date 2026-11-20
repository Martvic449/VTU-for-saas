@extends('layouts.app')
@section('title', 'Profile settings')
@section('content')
<div class="section-head">
  <h1 class="title">Profile settings</h1>
  <p>Manage your account email address and verification status.</p>
</div>
<div class="card" style="max-width:620px">
  <h3 style="margin-top:0">Email address</h3>
  <p class="muted">Your email address is used for sign-in and important account notifications.</p>
  <div style="margin:14px 0">
    <span class="stat-label">Current verification status</span>
    @if($user->hasVerifiedEmail())
      <span class="pill successful">Verified</span>
    @else
      <span class="pill pending">Not verified</span>
    @endif
  </div>
  <form method="post" action="{{ route('profile.update') }}">
    @csrf
    @method('PATCH')
    <div class="field">
      <label for="profile-email">Email address</label>
      <input id="profile-email" name="email" type="email" value="{{ old('email', $user->email) }}" maxlength="190" autocomplete="email" required>
    </div>
    <div class="field">
      <label for="current-password">Current password</label>
      <input id="current-password" name="current_password" type="password" autocomplete="current-password" required>
      <small class="muted">Confirm your password to protect changes to your account email.</small>
    </div>
    <button class="btn" type="submit">Save email address</button>
  </form>
</div>
@endsection
