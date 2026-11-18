<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#101c31"><link rel="manifest" href="{{ url('/manifest.webmanifest') }}"><link rel="icon" href="{{ url('/icon.svg') }}" type="image/svg+xml">
    <title>@yield('title', 'VTU SaaS') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
      :root{--ink:#17243b;--muted:#718096;--line:#e6ebf2;--bg:#f5f7fb;--green:#0d9b76;--green-dark:#087b60;--nav:#101c31;--nav-muted:#a8b4c8;--surface:#fff;--danger:#c2413a;--shadow:0 8px 28px rgba(20,35,60,.055)}
      *{box-sizing:border-box;font-synthesis:none}body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.65 'DM Sans',Inter,ui-sans-serif,system-ui,-apple-system,'Segoe UI',sans-serif}h1,h2,h3,h4,.brand,.metric{font-family:'DM Sans',ui-sans-serif,system-ui,sans-serif}h1,h2,h3,h4{font-weight:700}a{color:inherit;text-decoration:none}button,input,select,textarea{font:inherit}.shell{min-height:100vh}.public-top{height:72px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 clamp(18px,5vw,76px)}.brand{font-size:19px;font-weight:700;letter-spacing:-.45px}.brand span{color:var(--green)}.public-nav{display:flex;gap:22px;align-items:center;color:#526079}.wrap{max-width:1240px;margin:0 auto;padding:34px 24px}.title{font-size:27px;line-height:1.2;letter-spacing:-.8px;margin:0 0 6px;font-weight:700}.muted{color:var(--muted)}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:17px;margin:24px 0}.card{background:var(--surface);border:1px solid var(--line);border-radius:15px;padding:21px;box-shadow:var(--shadow)}.metric{font-size:28px;font-weight:700;letter-spacing:-1px;margin-top:7px}.btn{border:0;border-radius:9px;padding:11px 16px;background:var(--green);color:#fff;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:7px;transition:background .15s,transform .15s}.btn:hover{background:var(--green-dark);transform:translateY(-1px)}.btn:focus-visible,.password-toggle:focus-visible,a:focus-visible{outline:3px solid #49b69a;outline-offset:3px}.btn.secondary{background:#edf2f7;color:#26364e}.btn.secondary:hover{background:#e2e9f1}.btn.danger{background:var(--danger)}.field{display:grid;gap:6px;margin:14px 0}.field label{font-size:13px;font-weight:600;color:#47566d}.field input,.field select,.field textarea{width:100%;min-width:0;border:1px solid #d9e0ea;border-radius:9px;padding:12px;font:inherit;background:#fff;color:var(--ink);outline:none}.field input:focus,.field select:focus,.field textarea:focus{border-color:#49b69a;box-shadow:0 0 0 3px #0d9b7618}.password-control{position:relative;display:flex;align-items:center}.password-control input{padding-right:76px}.password-toggle{position:absolute;right:7px;top:50%;transform:translateY(-50%);border:0;border-radius:7px;background:#edf7f3;color:var(--green-dark);font-size:12px;font-weight:700;padding:7px 10px;cursor:pointer}.password-toggle:hover{background:#dff2eb}.form{max-width:460px;margin:54px auto}.form h1{margin:8px 0 6px;font-size:26px}.alert{padding:12px 14px;border-radius:10px;background:#e8f7f1;color:#12654f;margin:14px 0}.error{color:#b42318;font-size:13px}.table-wrap{overflow:auto;background:#fff;border:1px solid var(--line);border-radius:13px;box-shadow:var(--shadow)}table{width:100%;border-collapse:collapse;min-width:650px}th,td{text-align:left;padding:13px 15px;border-bottom:1px solid var(--line);font-size:13px}th{background:#f9fafc;color:#758197;font-size:11px;text-transform:uppercase;letter-spacing:.07em;font-weight:700}tr:last-child td{border-bottom:0}.pill{display:inline-block;padding:4px 9px;border-radius:99px;background:#eef2f6;color:#45556d;font-size:11px;font-weight:700;text-transform:capitalize}.pill.active,.pill.successful,.pill.approved,.pill.resolved{background:#e7f7f0;color:#087451}.pill.pending,.pill.submitted,.pill.under_review,.pill.open{background:#fff4dc;color:#966000}.pill.failed,.pill.suspended,.pill.rejected,.pill.closed{background:#fdecea;color:#b42318}.row{display:flex;gap:14px;align-items:center;justify-content:space-between;flex-wrap:wrap}.hero{padding:76px 0 60px;max-width:780px}.hero h1{font-size:clamp(36px,6vw,58px);line-height:1.08;letter-spacing:-2px;margin:12px 0 18px;font-weight:700}.hero p{font-size:18px;color:var(--muted);max-width:650px}.footer{padding:30px 22px;color:#8490a3;text-align:center;font-size:12px}
      .app-shell{min-height:100vh;display:grid;grid-template-columns:250px minmax(0,1fr)}.sidebar{background:var(--nav);color:#fff;min-height:100vh;padding:24px 15px;position:sticky;top:0;height:100vh;overflow-y:auto}.sidebar .brand{display:block;padding:3px 13px 26px;color:#fff}.sidebar .brand span{color:#42c8a2}.nav-label{color:#74849f;font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;padding:17px 13px 7px}.side-nav{display:grid;gap:4px}.side-nav a{padding:10px 13px;border-radius:8px;color:var(--nav-muted);font-size:13px;font-weight:600;display:flex;align-items:center;gap:10px}.side-nav a:hover,.side-nav a.active{background:#1b2b45;color:#fff}.side-nav a.active{box-shadow:inset 3px 0 #36c39a}.side-dot{width:7px;height:7px;border-radius:50%;background:#61728e}.side-nav a.active .side-dot{background:#42c8a2}.app-main{min-width:0}.app-topbar{height:72px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 32px;position:sticky;top:0;z-index:5}.app-topbar .user{display:flex;align-items:center;gap:12px}.avatar{width:34px;height:34px;border-radius:50%;background:#e3f5ef;color:#087b60;display:grid;place-items:center;font-weight:700}.app-content{max-width:1500px;margin:0 auto;padding:30px 32px}.app-content .wrap{max-width:none;margin:0;padding:0}.app-footer{padding:24px 32px;color:#98a2b3;font-size:11px}.menu-toggle{display:none}.section-head{margin:0 0 20px}.section-head p{margin:4px 0 0;color:var(--muted)}.actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.stack{display:grid;gap:16px}.stat-label{font-size:12px;color:#77849a;font-weight:600}.empty{padding:35px;text-align:center;color:var(--muted)}
      @media(max-width:900px){.app-shell{grid-template-columns:1fr}.sidebar{position:fixed;z-index:20;left:-270px;width:250px;transition:left .2s}.sidebar.open{left:0}.menu-toggle{display:inline-flex}.app-topbar{padding:0 18px}.app-content{padding:24px 16px}.grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
      @media(max-width:620px){.grid{grid-template-columns:1fr}.public-top{height:auto;min-height:66px;gap:12px;flex-wrap:wrap;padding:14px 18px}.public-nav{gap:12px;flex-wrap:wrap}.wrap{padding:24px 16px}.title{font-size:23px}.hero{padding:44px 0 34px}.hero h1{font-size:clamp(34px,10vw,46px);letter-spacing:-1.5px}.hero p{font-size:16px}.app-topbar{height:64px}.app-footer{padding:18px 16px}}
    </style>
    @stack('head')
</head>
<body><div class="shell">
@if(auth()->check())
@php($user=auth()->user())
<div class="app-shell">
  <aside class="sidebar" id="appSidebar"><a class="brand" href="{{ route('dashboard') }}">{{ config('app.name', 'VTU SaaS') }}</a>
    <div class="nav-label">Workspace</div><nav class="side-nav">
      @if($user->hasAnyRole(['super-admin','operations-admin','finance-admin','kyc-reviewer']))
        <a href="{{ route('platform.dashboard') }}"><i class="side-dot"></i>Overview</a>
        @if($user->hasAnyRole(['super-admin','operations-admin']))<a href="{{ route('platform.users') }}"><i class="side-dot"></i>Users</a>@endif
        @if($user->hasAnyRole(['super-admin','operations-admin']))<a href="{{ route('platform.tenants') }}"><i class="side-dot"></i>Tenants</a><a href="{{ route('platform.integrations') }}"><i class="side-dot"></i>Services & providers</a>@endif
        @if($user->hasAnyRole(['super-admin','operations-admin','finance-admin']))<a href="{{ route('platform.transactions') }}"><i class="side-dot"></i>Transactions</a>@endif
        @if($user->hasAnyRole(['super-admin','finance-admin']))<a href="{{ route('platform.finance') }}"><i class="side-dot"></i>Finance & ledger</a><a href="{{ route('platform.reports') }}"><i class="side-dot"></i>Reports & exports</a>@endif
        @if($user->hasAnyRole(['super-admin','kyc-reviewer']))<a href="{{ route('platform.kyc') }}"><i class="side-dot"></i>KYC reviews</a>@endif
        @if($user->hasAnyRole(['super-admin','operations-admin']))<a href="{{ route('platform.support') }}"><i class="side-dot"></i>Support tickets</a><a href="{{ route('platform.subscriptions') }}"><i class="side-dot"></i>Plans & subscriptions</a><a href="{{ route('platform.api-activity') }}"><i class="side-dot"></i>API activity</a><a href="{{ route('platform.security') }}"><i class="side-dot"></i>Audit & risk</a>@endif
      @elseif($user->hasRole('tenant-owner') && request()->attributes->get('tenant'))
        <a href="{{ route('tenant.dashboard') }}"><i class="side-dot"></i>Business dashboard</a><a href="{{ route('tenant.developers') }}"><i class="side-dot"></i>Developer API</a><a href="{{ route('dashboard') }}"><i class="side-dot"></i>Customer account</a>
      @elseif($user->hasRole('tenant-owner'))
        <a href="{{ route('dashboard') }}"><i class="side-dot"></i>My account</a>
      @else
        <a href="{{ route('dashboard') }}"><i class="side-dot"></i>My dashboard</a><a href="{{ route('support.index') }}"><i class="side-dot"></i>Help & support</a>
      @endif
    </nav>
    <div class="nav-label">Account</div><nav class="side-nav"><a href="{{ route('support.index') }}"><i class="side-dot"></i>Support centre</a><form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="side-nav a" style="width:100%;border:0;background:none;text-align:left;cursor:pointer"><i class="side-dot"></i>Sign out</button></form></nav>
  </aside>
  <section class="app-main"><header class="app-topbar"><div class="row" style="justify-content:flex-start"><button class="btn secondary menu-toggle" onclick="document.getElementById('appSidebar').classList.toggle('open')" type="button">☰</button><div><div style="font-weight:700;font-size:14px">{{ config('app.name','VTU SaaS') }}</div><div class="muted" style="font-size:11px">{{ $user->hasAnyRole(['super-admin','operations-admin','finance-admin','kyc-reviewer']) ? 'Platform workspace' : 'Customer workspace' }}</div></div></div><div class="user"><div class="avatar">{{ strtoupper(substr($user->name,0,1)) }}</div><div><div style="font-size:12px;font-weight:700">{{ $user->name }}</div><div class="muted" style="font-size:11px">{{ $user->getRoleNames()->first() ?? 'Customer' }}</div></div></div></header>
    @if(session('status') || $errors->any())<div class="app-content" style="padding-bottom:0">@if(session('status'))<div class="alert">{{ session('status') }}</div>@endif @if($errors->any())<div class="alert" style="background:#fff0ef;color:#a52b24">{{ $errors->first() }}</div>@endif</div>@endif
    <main class="app-content">@yield('content')</main><footer class="app-footer"><div class="row"><span>Secure digital services infrastructure · {{ date('Y') }}</span><span><a href="{{ route('legal.privacy') }}">Privacy</a> · <a href="{{ route('legal.terms') }}">Terms</a></span></div></footer>
  </section>
</div>
@else
<header class="public-top"><a class="brand" href="{{ route('home') }}">{{ config('app.name', 'VTU SaaS') }}</a><nav class="public-nav"><a href="{{ route('home') }}">Home</a><a href="{{ route('legal.privacy') }}">Privacy</a><a href="{{ route('legal.terms') }}">Terms</a><a href="{{ route('login') }}">Sign in</a><a class="btn" href="{{ route('account.type') }}">Get started</a></nav></header>
@if(session('status'))<div class="wrap" style="padding-bottom:0"><div class="alert">{{ session('status') }}</div></div>@endif
<main>@yield('content')</main><footer class="footer"><div>Secure digital services infrastructure · {{ date('Y') }}</div><div style="margin-top:8px"><a href="{{ route('legal.privacy') }}">Privacy Policy</a> &nbsp;·&nbsp; <a href="{{ route('legal.terms') }}">Terms &amp; Conditions</a> &nbsp;·&nbsp; <a href="mailto:{{ config('app.support_email', 'support@example.com') }}">Contact</a></div></footer>
@endif
</div>
@stack('scripts')
<script>
document.addEventListener('click', function (event) {
  const button = event.target.closest('[data-password-toggle]');
  if (!button) return;
  const input = document.getElementById(button.dataset.passwordToggle);
  if (!input) return;
  const reveal = input.type === 'password';
  input.type = reveal ? 'text' : 'password';
  button.textContent = reveal ? 'Hide' : 'Show';
  button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
});
if ('serviceWorker' in navigator && location.protocol === 'https:') { window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {})); }
</script>
</body></html>
