@extends('layouts.app')
@section('title','Reset password')
@section('content')<div class="wrap"><div class="card form"><h1>Reset your password</h1><p class="muted">Enter your account email. If it matches an account, we will send a secure reset link.</p><form method="post" action="{{ route('password.email') }}">@csrf<div class="field"><label>Email address</label><input type="email" name="email" required autocomplete="email"></div><button class="btn">Send reset link</button></form><p class="muted"><a href="{{ route('login') }}">Back to sign in</a></p></div></div>@endsection
