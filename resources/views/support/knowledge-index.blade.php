@extends('layouts.app')
@section('title','Help Centre')
@section('content')
<div class="section-head"><span class="section-kicker">Help & learning</span><h1 class="title">Help Centre</h1><p class="section-intro">Find guides, answers and step-by-step tutorials for using the platform.</p></div>
@if($announcements->isNotEmpty())
<div class="stack" style="gap:10px;margin-bottom:20px">
@foreach($announcements as $announcement)
<div class="card" style="border-left:4px solid {{ $announcement->priority === 'critical' ? 'var(--danger)' : ($announcement->priority === 'important' ? 'var(--warning)' : 'var(--green)') }}">
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
<div style="min-width:0;flex:1"><span class="section-kicker">{{ ucfirst($announcement->priority) }} announcement</span><strong style="display:block;margin-top:4px">{{ $announcement->title }}</strong><p style="margin:6px 0 0;white-space:pre-line">{{ $announcement->message }}</p></div>
@if($announcement->is_dismissible)
<form method="post" action="{{ route('announcements.dismiss', $announcement->id) }}">@csrf<button class="btn secondary" type="submit">Dismiss</button></form>
@endif
</div>
</div>
@endforeach
</div>
@endif
<form method="get" class="card" style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:end;margin-bottom:20px"><div class="field" style="margin:0"><label for="help-search">Search articles</label><input id="help-search" name="q" value="{{ request('q') }}" placeholder="Search a topic, feature or question"></div><button class="btn" type="submit">Search</button></form>
@if($categories->isNotEmpty())<div class="actions" style="margin-bottom:20px"><a class="btn secondary" href="{{ route('support.index') }}">All topics</a>@foreach($categories as $category)<a class="btn secondary" href="{{ route('support.index', ['category'=>$category->slug]) }}">{{ $category->name }}</a>@endforeach</div>@endif
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(250px,1fr))">
@forelse($articles as $article)<article class="card" style="display:flex;flex-direction:column;gap:8px"><span class="section-kicker">{{ $article->category_name ?: 'Guide' }}</span><h2 style="font-size:18px;margin:0">{{ $article->title }}</h2><p class="muted" style="margin:0">{{ $article->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($article->content),130) }}</p><div style="margin-top:auto;padding-top:10px"><a class="btn secondary" href="{{ route('support.article',$article->slug) }}">Read article →</a></div></article>
@empty<div class="card empty" style="grid-column:1/-1">No published articles match this search yet. Contact support if you need help.</div>@endforelse
</div>
<div style="margin-top:18px">{{ $articles->links() }}</div>
<div class="card" style="margin-top:24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap"><div><strong>Still need help?</strong><p class="muted" style="margin:4px 0 0">Send our support team a message and track your request.</p></div><a class="btn" href="{{ route('support.contact') }}">Contact support</a></div>
@endsection