@extends('layouts.app')
@section('title','Tenant management')
@section('content')
<div class="section-head"><span class="section-kicker">Platform administration</span><h1 class="title">Tenant management</h1><p class="section-intro">Review reseller applications, approve businesses and complete DNS ownership and HTTPS activation.</p></div>
@if($errors->any())<div class="alert" style="background:#fff0ef;color:#a52b24">{{ $errors->first() }}</div>@endif
@if(session('domain_challenge'))@php($challenge=session('domain_challenge'))<div class="card" style="margin:18px 0;border-color:#e9c46a"><h3 style="margin-top:0">DNS verification record for {{ $challenge['hostname'] }}</h3><p>Add this TXT record in the DNS manager for the reseller domain. It expires at {{ $challenge['expires_at'] }}.</p><div class="field"><label>Record type</label><input readonly value="{{ $challenge['record_type'] }}"></div><div class="field"><label>Record name / host</label><input readonly value="{{ $challenge['record_name'] }}"></div><div class="field"><label>Record value</label><input readonly value="{{ $challenge['record_value'] }}"></div><p class="muted">After DNS propagation, use Verify DNS below. The token is stored hashed and cannot be retrieved again; issue a new challenge if it expires.</p></div>@endif
@if(auth()->user()->hasRole('super-admin'))<div class="card" style="margin:22px 0"><h3 style="margin-top:0">Create tenant</h3><form method="post" action="{{ route('platform.tenants.create') }}" class="row">@csrf<div class="field" style="flex:2;min-width:180px;margin:0"><label>Business name</label><input name="name" required maxlength="160"></div><div class="field" style="flex:1;min-width:150px;margin:0"><label>Subdomain slug</label><input name="slug" required maxlength="50" pattern="[A-Za-z0-9_-]+"></div><div class="field" style="flex:1;min-width:190px;margin:0"><label>Owner email (optional)</label><input name="owner_email" type="email" placeholder="Existing account email"></div><button class="btn" type="submit">Create tenant</button></form></div>@endif
@forelse($tenants as $tenant)
<div class="card" style="margin:18px 0">
 <div class="row"><div><h2 style="margin:0">{{ $tenant->name }}</h2><p class="muted" style="margin:4px 0">Business reference: {{ $tenant->slug }}</p></div><span class="pill {{ $tenant->status }}">{{ ucfirst($tenant->status) }}</span></div>
 @if(auth()->user()->hasRole('super-admin'))<form method="post" action="{{ route('platform.tenants.status',$tenant->id) }}" class="row" style="justify-content:flex-start;margin:15px 0">@csrf @method('PATCH')<div class="field" style="margin:0;min-width:140px"><label>Application status</label><select name="status"><option value="pending" @selected($tenant->status==='pending')>Pending</option><option value="active" @selected($tenant->status==='active')>Active / approved</option><option value="suspended" @selected($tenant->status==='suspended')>Suspended</option></select></div><div class="field" style="flex:1;min-width:180px;margin:0"><label>Reason (audit record)</label><input name="reason" required minlength="8" maxlength="500" placeholder="Reason for status change"></div><button class="btn secondary" type="submit">Update application</button></form>@endif
 <h3>Business domains</h3>
 @forelse($tenant->domains as $domain)
 <div style="border:1px solid var(--line);border-radius:12px;padding:15px;margin:10px 0">
  <div class="row"><strong>{{ $domain->hostname }}</strong><span class="pill {{ $domain->verified_at ? 'active' : 'pending' }}">DNS {{ $domain->verified_at ? 'verified' : 'pending' }}</span><span class="pill {{ $domain->tls_status==='active' ? 'active' : 'pending' }}">HTTPS {{ $domain->tls_status }}</span></div>
  @if(auth()->user()->hasRole('super-admin'))
   <div class="row" style="justify-content:flex-start;margin-top:12px">
    @if(!$domain->verified_at)<form method="post" action="{{ route('platform.tenant-domains.challenge',$domain->id) }}">@csrf<button class="btn secondary" type="submit">Generate DNS TXT challenge</button></form><form method="post" action="{{ route('platform.tenant-domains.verify',$domain->id) }}" class="row">@csrf<div class="field" style="margin:0"><input name="reason" required minlength="8" maxlength="500" placeholder="Verification reason"></div><button class="btn" type="submit">Verify DNS record</button></form>@elseif($domain->tls_status!=='active')<form method="post" action="{{ route('platform.tenant-domains.tls',$domain->id) }}" class="row">@csrf<div class="field" style="margin:0"><input name="reason" required minlength="8" maxlength="500" placeholder="HTTPS activation reason"></div><button class="btn" type="submit">Check HTTPS & activate</button></form>@else<span class="muted">Domain is verified and HTTPS is active.</span>@endif
   </div>
  @endif
 </div>
 @empty<p class="muted">No domain has been assigned to this tenant.</p>@endforelse
 <p class="muted" style="font-size:12px">Created {{ $tenant->created_at->format('d M Y H:i') }}</p>
</div>
@empty<div class="card">No reseller tenants have been created yet.</div>@endforelse
<div style="margin-top:18px">{{ $tenants->links() }}</div>
@endsection