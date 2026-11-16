@extends('layouts.app')
@section('title','Verify sign in')
@section('content')<div class="wrap"><div class="card form"><h1>Verify your sign in</h1><p class="muted">Enter the six-digit code from your authenticator app.</p>@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif<form method="post" action="{{ route('mfa.challenge') }}">@csrf<div class="field"><label>Authenticator code</label><input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus></div><button class="btn" type="submit">Verify</button></form></div></div>@endsection
