{{-- resources/views/bible/verse-study.blade.php — Verse study with accordions A/B/C --}}
@extends('layouts.app')
@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-[24px] sm:text-[26px] font-extrabold leading-none text-stone-900">
            {{ $book['name_ar'] ?? 'دراسة الآية' }} <span class="font-normal text-stone-500">{{ $osis }}</span>
        </h1>
        <p class="mt-1 text-[12px] text-stone-500">{{ $book['name_en'] ?? '' }} · {{ $verse['osis_id'] ?? $osis }} · دراسة كلمة بكلمة</p>
    </div>
    <div class="flex items-center gap-2">
        @if($prevVerse)
            <a href="{{ url('/verse/'.$prevVerse['osis_id']) }}" class="h-9 px-3.5 rounded-full border border-stone-200 bg-white text-[13px] font-semibold text-stone-700 hover:bg-stone-50">‹ {{ $prevVerse['osis_id'] }}</a>
        @endif
        @if($nextVerse)
            <a href="{{ url('/verse/'.$nextVerse['osis_id']) }}" class="h-9 px-3.5 rounded-full bg-stone-900 text-white text-[13px] font-bold hover:bg-black">{{ $nextVerse['osis_id'] }} ›</a>
        @endif
        <a href="{{ url('/read/'.($book['slug']??'al-takwin').'/'.($verse['chapter']??1)) }}" class="h-9 px-3.5 rounded-full border border-stone-200 bg-white text-[13px] font-semibold text-stone-700">الإصحاح</a>
    </div>
</div>

{{-- Header: Large vocalized Arabic + Hebrew/Greek original --}}
@if($verse)
<div class="bg-white rounded-[20px] border border-stone-200 p-6 sm:p-7 shadow-[0_10px_30px_rgba(0,0,0,.04)]">
    <div class="text-[11px] font-bold tracking-wide text-red-600 mb-2">{{ $verse['osis_id'] }} • الإصحاح {{ $verse['chapter'] }} : الآية {{ $verse['verse'] }}</div>
    <div class="font-arabic-text text-[26px] sm:text-[28px] leading-[2] flex flex-wrap gap-x-2 gap-y-1">
        @foreach($tokens as $tok)
            <span class="word-token" data-strong="{{ $tok->strong_id }}" data-arabic="{{ $tok->arabic_word }}" data-source="{{ $tok->source_word }}" data-translit="{{ $tok->transliteration }}" data-gloss-ar="{{ $tok->lexicon->short_gloss_ar ?? '' }}" data-gloss="{{ $tok->gloss_en }}" data-gloss-en="{{ $tok->gloss_en }}" data-morph="{{ $tok->morphology }}">{{ $tok->arabic_word }}</span>
        @endforeach
    </div>
    <div class="mt-4 pt-4 border-t border-stone-100 flex flex-wrap gap-1.5" dir="ltr">
        @foreach($tokens as $tok)
            <span class="font-hebrew text-[16px] text-stone-600 border border-stone-100 rounded-full px-2.5 py-1">{{ $tok->source_word }}</span>
        @endforeach
    </div>
</div>
@endif

{{-- Accordion Sections --}}
<div class="mt-6">
    {{-- A: Interlinear --}}
    <details class="accordion" open x-data="{open:true}">
        <summary @click.prevent="$el.parentElement.open = !$el.parentElement.open">
            <span class="flex items-center gap-2"><span class="w-7 h-7 grid place-items-center rounded-full bg-red-50 text-red-600 text-[12px] font-bold">أ</span> دراسة كلمة بكلمة — Interlinear</span>
            <span class="text-stone-400">⌄</span>
        </summary>
        <div class="p-0">
            <div class="hidden sm:grid grid-cols-[56px_1.2fr_1fr_1fr_90px_1.2fr] gap-0 text-[11px] font-bold tracking-wide text-stone-400 px-4 py-2 border-b border-stone-100 bg-stone-50">
                <span>#</span><span>العربية</span><span>الأصل</span><span>النطق</span><span>سترونغ</span><span>المعنى / الصرف</span>
            </div>
            @foreach($interlinear as $row)
                <div class="sm:grid sm:grid-cols-[56px_1.2fr_1fr_1fr_90px_1.2fr] sm:gap-3 px-4 py-4 border-b border-stone-100 last:border-0 hover:bg-stone-50/60 transition bg-white sm:bg-transparent rounded-xl sm:rounded-none border sm:border-0 shadow-sm sm:shadow-none mb-3 sm:mb-0">
                    <div class="hidden sm:flex items-center gap-2">
                        <span class="w-7 h-7 grid place-items-center rounded-full bg-stone-900 text-white text-[11px] font-bold">{{ $row['position'] }}</span>
                    </div>
                    <div class="sm:hidden flex items-center justify-between mb-2">
                        <span class="w-6 h-6 grid place-items-center rounded-full bg-stone-900 text-white text-[10px] font-bold">{{ $row['position'] }}</span>
                        <a href="{{ url('/lexicon/'.$row['strong_id']) }}" class="inline-flex px-2.5 py-1 rounded-full bg-red-50 border border-red-100 text-[11px] font-bold text-red-700">{{ $row['strong_id'] }}</a>
                    </div>
                    <div class="sm:block flex gap-3">
                        <div class="sm:hidden text-[10px] font-bold text-stone-400 w-12 shrink-0 pt-1">العربية</div>
                        <div><a href="{{ url('/lexicon/'.$row['strong_id']) }}" class="font-bold text-stone-900 hover:text-red-600 font-arabic-text text-[16px]">{{ $row['arabic_word'] }}</a><div class="text-[11px] text-stone-400">{{ $row['arabic_clean'] ?? '' }}</div></div>
                    </div>
                    <div class="sm:block flex gap-3">
                        <div class="sm:hidden text-[10px] font-bold text-stone-400 w-12 shrink-0">الأصل</div>
                        <div class="font-hebrew text-[16px] text-stone-700" dir="ltr">{{ $row['source_word'] }}</div>
                    </div>
                    <div class="sm:block flex gap-3">
                        <div class="sm:hidden text-[10px] font-bold text-stone-400 w-12 shrink-0">النطق</div>
                        <div class="font-greek text-[13px] text-stone-600">{{ $row['transliteration'] }}</div>
                    </div>
                    <div class="hidden sm:block"><a href="{{ url('/lexicon/'.$row['strong_id']) }}" class="inline-flex px-2.5 py-1 rounded-full bg-red-50 border border-red-100 text-[12px] font-bold text-red-700 hover:bg-red-100">{{ $row['strong_id'] }}</a></div>
                    @php
                        $arabicGloss = $row['lexicon']['short_gloss_ar'] ?? null;
                        $cleanGloss = $arabicGloss ? $arabicGloss : trim(str_replace(['{', '}'], '', str_replace(';', ' ', $row['gloss_en'] ?? '')));
                        // تبسيط الصرف تلقائياً
                        $rawMorph = $row['morphology'] ?? '';
                        $simpleMorph = str_contains($rawMorph, 'V') ? 'فعل' : (str_contains($rawMorph, 'N') ? 'اسم' : (str_contains($rawMorph, 'R') ? 'حرف' : '—'));
                        if(str_contains($rawMorph, 'f')) $simpleMorph .= ' مؤنث';
                        elseif(str_contains($rawMorph, 'm')) $simpleMorph .= ' مذكر';
                    @endphp
                    <div class="text-[13px] leading-[1.6] text-stone-800">
                        <span class="font-semibold text-stone-900">{{ $cleanGloss }}</span>
                        @if($arabicGloss && !empty($row['gloss_en']))
                            <span class="block text-[11px] text-stone-400 font-mono mt-0.5">{{ trim(str_replace(['{','}'],'',$row['gloss_en'])) }}</span>
                        @endif
                        <span class="inline-block text-stone-500 text-[11px] bg-stone-100 px-2 py-0.5 rounded-full mt-1">{{ $simpleMorph }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </details>

    {{-- B: Commentaries --}}
    <details class="accordion" @if($commentaries && $commentaries->count()) open @endif>
        <summary>
            <span class="flex items-center gap-2"><span class="w-7 h-7 grid place-items-center rounded-full bg-stone-900 text-white text-[12px] font-bold">ب</span> تفاسير الآباء والرعاة <span class="text-stone-400 font-normal text-[12px]">— {{ $commentaries?->count() ?? 0 }} تفسير</span></span>
            <span class="text-stone-400">⌄</span>
        </summary>
        <div class="p-4 space-y-3">
            @forelse($commentaries as $c)
                <div class="rounded-xl border border-stone-200 bg-white p-4">
                    <div class="flex flex-wrap items-center gap-2 text-[13px]">
                        <span class="font-bold text-stone-900">{{ $c->title ?? $c->verse_start }}</span>
                        <span class="px-2 py-0.5 rounded-full bg-stone-100 text-stone-600 text-[11px]">{{ $c->level==='word'?'كلمة':($c->level==='verse'?'آية':'مقطع') }}</span>
                        <span class="text-stone-500">— {{ $c->author_name }}</span>
                    </div>
                    <div class="mt-3 text-[14px] leading-[1.9] text-stone-700 prose">{!! $c->body_html !!}</div>
                    @if($c->strong_id)
                        <a href="{{ url('/lexicon/'.$c->strong_id) }}" class="mt-3 inline-flex text-[12px] font-semibold text-red-600 hover:underline">→ {{ $c->strong_id }} في المعجم</a>
                    @endif
                </div>
            @empty
                <p class="text-[13px] text-stone-500">لا توجد تفاسير لهذه الآية حالياً — سيتم إضافة تفاسير الأب متى المسكين والآباء قريباً.</p>
            @endforelse
        </div>
    </details>

    {{-- C: Cross references --}}
    <details class="accordion" @if($crossReferences && $crossReferences->count()) open @endif>
        <summary>
            <span class="flex items-center gap-2"><span class="w-7 h-7 grid place-items-center rounded-full bg-white border border-stone-200 text-stone-600 text-[12px] font-bold">ج</span> مراجع موازية / Cross-references <span class="text-stone-400 font-normal text-[12px]">— {{ $crossReferences?->count() ?? 0 }}</span></span>
            <span class="text-stone-400">⌄</span>
        </summary>
        <div class="p-4">
            @if($crossReferences && $crossReferences->count())
                <div class="flex flex-wrap gap-2">
                    @foreach($crossReferences as $cr)
                        <a href="{{ url('/verse/'.$cr->osis_id) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-stone-200 bg-stone-50 text-[13px] font-semibold text-stone-700 hover:bg-white hover:border-red-200 hover:text-red-600 transition">{{ $cr->osis_id }} <span class="text-stone-400 font-normal">{{ $cr->book->name_ar ?? '' }}</span></a>
                    @endforeach
                </div>
            @else
                <p class="text-[13px] text-stone-500">لا توجد مراجع موازية — تُحسب تلقائياً من مشاركة أرقام سترونغ.</p>
            @endif
        </div>
    </details>
</div>
@endsection
