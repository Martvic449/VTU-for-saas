@extends('layouts.app')
@section('title','Sign in')
@section('content')<div class="wrap"><div class="card form"><h1>Welcome back</h1><p class="muted">Sign in to your VTU SaaS account.</p>
@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('login') }}">@csrf<div class="field"><label>Email address</label><input name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></div><div class="field"><label>Password</label><input name="password" type="password" required autocomplete="current-password"></div><button class="btn" type="submit">Sign in</button></form>
<p class="muted"><a href="{{ route('password.request') }}" style="color:var(--green);font-weight:700">Forgot password?</a></p><p class="muted">New here? <a href="{{ route('register') }}" style="color:var(--green);font-weight:700">Create an account</a></p></div></div>@endsection
