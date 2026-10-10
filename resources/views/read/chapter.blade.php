{{-- resources/views/read/chapter.blade.php — Tier 1: /read/{book}/{chapter} --}}
@extends('layouts.app')
@section('content')
<div style="display:flex; justify-content:space-between; align-items:end; gap:12px; flex-wrap:wrap; margin-bottom:16px">
    <div>
        <h1 style="font-size:28px; font-weight:800; margin:0">{{ $bookModel->name_ar }} — الإصحاح {{ $chapter }}</h1>
        <p style="color:#6b7280; margin:4px 0 0">{{ $bookModel->name_en }} Chapter {{ $chapter }} &middot; {{ $verses->count() }} آية &middot; {{ $bookModel->code }}.{{ $chapter }}</p>
    </div>
    <div style="display:flex; gap:8px">
        @if($prevChapter)
            <a href="{{ url('/read/'.$prevChapter['book_slug'].'/'.$prevChapter['chapter']) }}" style="padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; text-decoration:none; color:#374151">‹ {{ $prevChapter['book_name_ar'] }} {{ $prevChapter['chapter'] }}</a>
        @endif
        @if($nextChapter)
            <a href="{{ url('/read/'.$nextChapter['book_slug'].'/'.$nextChapter['chapter']) }}" style="padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; text-decoration:none; color:#374151">{{ $nextChapter['book_name_ar'] }} {{ $nextChapter['chapter'] }} ›</a>
        @endif
    </div>
</div>

{{-- Verses --}}
<div class="prose" style="display:flex; flex-direction:column; gap:14px">
@foreach($verses as $verse)
    <div id="{{ $verse->osis_id }}" style="padding:12px; border:1px solid #f3f4f6; border-radius:12px; background:#fff">
        <div style="font-weight:700; color:#dc2626; font-size:13px; margin-bottom:6px">
            <a href="{{ url('/verse/'.$verse->osis_id) }}" style="color:#dc2626; text-decoration:none">{{ $verse->osis_id }}</a>
            <span style="color:#9ca3af">— آية {{ $verse->verse }}</span>
        </div>
        <p style="font-size:20px; line-height:2; margin:0; display:flex; flex-wrap:wrap; gap:4px">
            @foreach($verse->tokens as $tok)
                <span class="word-token"
                    data-strong="{{ $tok->strong_id }}"
                    data-transliteration="{{ $tok->transliteration }}"
                    data-arabic="{{ $tok->arabic_word }}"
                    data-source="{{ $tok->source_word }}"
                    data-gloss="{{ $tok->clean_gloss }}"
                    data-morphology="{{ $tok->morphology }}"
                    data-lemma="{{ $tok->lexicon->lemma ?? '' }}"
                    title="{{ $tok->arabic_word }} ← {{ $tok->source_word }} ({{ $tok->strong_id }})"
                    onclick="openDrawer(this)">
                    {{ $tok->arabic_word }}
                </span>
                @if(!$loop->last)<span style="color:#e5e7eb">·</span>@endif
            @endforeach
        </p>
        {{-- Interlinear tiny --}}
        <div style="margin-top:8px; display:flex; flex-wrap:wrap; gap:6px; font-size:12px; color:#6b7280">
            @foreach($verse->tokens as $tok)
                <span style="border:1px solid #e5e7eb; padding:2px 6px; border-radius:999px">{{ $tok->transliteration }} <span style="color:#9ca3af">({{ $tok->strong_id }})</span></span>
            @endforeach
        </div>
    </div>
@endforeach
</div>

@if($commentaries && $commentaries->count())
<div style="margin-top:32px; padding:20px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:12px">
    <h2 style="font-size:16px; font-weight:700; margin:0 0 12px">تفسير / تعليقات لهذا الإصحاح ({{ $commentaries->count() }})</h2>
    @foreach($commentaries as $c)
        <div style="margin-bottom:16px; padding:12px; background:#fff; border-radius:8px; border:1px solid #f3f4f6">
            <div style="font-weight:600; color:#111">{{ $c->title ?? $c->verse_start }} <span style="font-weight:400; color:#6b7280">— {{ $c->author_name }} ({{ $c->level }})</span></div>
            <div style="font-size:14px; color:#374151; margin-top:6px">{!! $c->body_html !!}</div>
        </div>
    @endforeach
</div>
@endif

{{-- Client-side Drawer for progressive disclosure --}}
<div id="drawer" class="drawer" role="dialog" aria-label="دراسة الكلمة">
    <div style="padding:16px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center">
        <strong id="drawer-strong" style="color:#dc2626"></strong>
        <button onclick="closeDrawer()" style="border:none; background:#f3f4f6; padding:6px 10px; border-radius:8px; cursor:pointer">✕</button>
    </div>
    <div style="padding:16px; display:flex; flex-direction:column; gap:10px">
        <div id="drawer-arabic" style="font-size:22px; font-weight:700"></div>
        <div id="drawer-source" style="font-size:18px; color:#6b7280"></div>
        <div id="drawer-trans" style="font-size:14px; color:#6b7280"></div>
        <div id="drawer-gloss" style="font-size:14px"></div>
        <div id="drawer-morph" style="font-size:12px; color:#9ca3af"></div>
        <a id="drawer-link" href="#" style="margin-top:8px; display:inline-block; padding:8px 12px; background:#dc2626; color:#fff; border-radius:8px; text-decoration:none; text-align:center">افتح المعجم</a>
    </div>
</div>
<div id="drawer-backdrop" onclick="closeDrawer()" style="position:fixed; inset:0; background:rgba(0,0,0,.2); display:none; z-index:40"></div>

@push('scripts')
<script>
function openDrawer(el){
    const d=document.getElementById('drawer'), b=document.getElementById('drawer-backdrop');
    document.getElementById('drawer-strong').textContent = el.dataset.strong || '';
    document.getElementById('drawer-arabic').textContent = el.dataset.arabic || '';
    document.getElementById('drawer-source').textContent = el.dataset.source || '';
    document.getElementById('drawer-trans').textContent = el.dataset.transliteration ? 'النطق: '+el.dataset.transliteration : '';
    document.getElementById('drawer-gloss').textContent = el.dataset.gloss ? 'المعنى: '+el.dataset.gloss : '';
    document.getElementById('drawer-morph').textContent = el.dataset.morphology ? 'الصرف: '+el.dataset.morphology : '';
    const link=document.getElementById('drawer-link');
    link.href = '/lexicon/'+(el.dataset.strong||'');
    d.classList.add('open'); b.style.display='block';
}
function closeDrawer(){
    document.getElementById('drawer').classList.remove('open');
    document.getElementById('drawer-backdrop').style.display='none';
}
</script>
@endpush
@endsection
