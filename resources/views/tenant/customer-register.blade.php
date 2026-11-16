@extends('layouts.app')
@section('title','Create customer account · '.$tenant->name)
@section('content')
<div class="form card"><span class="pill">{{ $tenant->name }}</span><h1>Create your account</h1><p class="muted">Register to fund your reseller wallet and buy available digital services.</p>
@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('tenant.customer.register.store') }}">@csrf
<div class="field"><label>Full name</label><input name="name" value="{{ old('name') }}" required autocomplete="name"></div>
<div class="field"><label>Email address</label><input name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></div>
<div class="field"><label>Password</label><input name="password" type="password" required minlength="12" autocomplete="new-password"></div>
<div class="field"><label>Confirm password</label><input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></div>
<button class="btn" type="submit" style="width:100%">Create account</button></form>
<p class="muted" style="font-size:12px;margin-top:16px">Already registered? <a href="{{ route('login') }}" style="color:var(--green);font-weight:700">Sign in</a></p></div>
@endsection
