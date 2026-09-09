{{-- resources/views/verse/study.blade.php — Tier 2: /verse/{osis} --}}
@extends('layouts.app')
@section('content')
<div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px">
    <h1 style="font-size:26px; font-weight:800; margin:0">{{ $book['name_ar'] ?? 'دراسة الآية' }} {{ $osis }} <span style="font-weight:400; color:#6b7280; font-size:16px">— {{ $book['name_en'] ?? '' }}</span></h1>
    <div style="display:flex; gap:8px">
        @if($prevVerse)
            <a href="{{ url('/verse/'.$prevVerse['osis_id']) }}" style="padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; text-decoration:none; color:#374151">‹ {{ $prevVerse['osis_id'] }}</a>
        @endif
        @if($nextVerse)
            <a href="{{ url('/verse/'.$nextVerse['osis_id']) }}" style="padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; text-decoration:none; color:#374151">{{ $nextVerse['osis_id'] }} ›</a>
        @endif
    </div>
</div>

@if($verse)
<div style="padding:16px; border:1px solid #e5e7eb; border-radius:12px; background:#f9fafb; margin-bottom:20px">
    <div style="font-size:13px; color:#dc2626; font-weight:700">{{ $verse['osis_id'] }} • الإصحاح {{ $verse['chapter'] }} : الآية {{ $verse['verse'] }}</div>
    <div style="font-size:20px; margin-top:8px; line-height:2; display:flex; flex-wrap:wrap; gap:6px">
        @foreach($tokens as $tok)
            <span class="word-token" data-strong="{{ $tok->strong_id }}" data-arabic="{{ $tok->arabic_word }}" title="{{ $tok->gloss_en }}">
                {{ $tok->arabic_word }}
                <span style="font-size:11px; color:#9ca3af; display:block; text-align:center">{{ $tok->transliteration }}</span>
            </span>
        @endforeach
    </div>
</div>
@endif

{{-- Interlinear table --}}
<h2 style="font-size:16px; font-weight:700; margin:18px 0 8px">الدراسة كلمة بكلمة (Interlinear)</h2>
<div style="overflow:auto; border:1px solid #e5e7eb; border-radius:12px">
<table style="width:100%; border-collapse:collapse; font-size:14px">
    <thead style="background:#f9fafb">
        <tr>
            <th style="padding:10px; border-bottom:1px solid #e5e7eb; text-align:start">#</th>
            <th style="padding:10px; border-bottom:1px solid #e5e7eb; text-align:start">العربية</th>
            <th style="padding:10px; border-bottom:1px solid #e5e7eb; text-align:start">الأصل</th>
            <th style="padding:10px; border-bottom:1px solid #e5e7eb; text-align:start">النطق</th>
            <th style="padding:10px; border-bottom:1px solid #e5e7eb; text-align:start">سترونغ</th>
            <th style="padding:10px; border-bottom:1px solid #e5e7eb; text-align:start">المعنى / الصرف</th>
        </tr>
    </thead>
    <tbody>
    @foreach($interlinear as $row)
        <tr style="border-bottom:1px solid #f3f4f6">
            <td style="padding:10px">{{ $row['position'] }}</td>
            <td style="padding:10px; font-weight:600"><a href="{{ url('/lexicon/'.$row['strong_id']) }}" style="color:#2563eb; text-decoration:none">{{ $row['arabic_word'] }}</a></td>
            <td style="padding:10px; font-family:serif">{{ $row['source_word'] }}</td>
            <td style="padding:10px; color:#6b7280">{{ $row['transliteration'] }}</td>
            <td style="padding:10px"><a href="{{ url('/lexicon/'.$row['strong_id']) }}" style="padding:2px 8px; border:1px solid #e5e7eb; border-radius:999px; text-decoration:none; color:#dc2626; font-weight:600">{{ $row['strong_id'] }}</a></td>
            <td style="padding:10px; color:#374151">{{ $row['gloss_en'] }} <span style="color:#9ca3af">{{ $row['morphology'] }}</span></td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>

{{-- Commentaries --}}
@if($commentaries && $commentaries->count())
<h2 style="font-size:16px; font-weight:700; margin:24px 0 8px">التفاسير ({{ $commentaries->count() }})</h2>
@foreach($commentaries as $c)
    <div style="padding:14px; border:1px solid #e5e7eb; border-radius:12px; background:#fff; margin-bottom:12px">
        <div style="font-weight:700">{{ $c->title ?? $c->verse_start }} <span style="font-weight:400; color:#6b7280">— {{ $c->author_name }} • {{ $c->level }}</span></div>
        <div style="margin-top:8px; font-size:14px; color:#374151; line-height:1.8">{!! $c->body_html !!}</div>
        @if($c->strong_id)
            <div style="margin-top:8px"><a href="{{ url('/lexicon/'.$c->strong_id) }}" style="color:#2563eb; font-size:13px">← {{ $c->strong_id }} في المعجم</a></div>
        @endif
    </div>
@endforeach
@endif

{{-- Cross refs --}}
@if($crossReferences && $crossReferences->count())
<h2 style="font-size:16px; font-weight:700; margin:24px 0 8px">آيات مرتبطة (تشارك سترونغ)</h2>
<div style="display:flex; flex-wrap:wrap; gap:8px">
@foreach($crossReferences as $cr)
    <a href="{{ url('/verse/'.$cr->osis_id) }}" style="padding:8px 12px; border:1px solid #e5e7eb; border-radius:999px; text-decoration:none; color:#374151; background:#f9fafb">{{ $cr->osis_id }} <span style="color:#6b7280">{{ $cr->book->name_ar ?? '' }}</span></a>
@endforeach
</div>
@endif

{{-- Breadcrumb JSON is in layout via $seo --}}
@endsection
