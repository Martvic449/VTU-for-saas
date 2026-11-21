@extends('layouts.app')
@section('title',$article->title)
@section('content')
<div style="max-width:850px;margin:0 auto"><a class="muted" href="{{ route('support.index') }}">← Back to Help Centre</a>
<div class="section-head" style="margin-top:20px"><span class="section-kicker">{{ $article->category_name ?: 'Help guide' }}</span><h1 class="title">{{ $article->title }}</h1>@if($article->excerpt)<p class="section-intro">{{ $article->excerpt }}</p>@endif<div class="muted" style="font-size:12px;margin-top:12px">Updated {{ \Illuminate\Support\Carbon::parse($article->updated_at)->format('d M Y') }}</div></div>
<article class="card" style="padding:clamp(20px,4vw,38px);font-size:15px;line-height:1.9;overflow-wrap:anywhere">{!! nl2br(e($article->content)) !!}</article>
<div class="card" style="margin-top:18px"><strong>Was this article helpful?</strong><div class="actions" style="margin-top:12px"><form method="post" action="{{ route('support.article.feedback',$article->id) }}">@csrf<input type="hidden" name="helpful" value="1"><button class="btn {{ (string)$feedback === '1' ? '' : 'secondary' }}" type="submit">Yes, it helped</button></form><form method="post" action="{{ route('support.article.feedback',$article->id) }}">@csrf<input type="hidden" name="helpful" value="0"><button class="btn {{ (string)$feedback === '0' ? '' : 'secondary' }}" type="submit">Not really</button></form></div></div>
<p style="margin-top:18px">Need more help? <a href="{{ route('support.contact') }}" style="color:var(--green-dark);font-weight:700">Contact support</a>.</p></div>
@endsection