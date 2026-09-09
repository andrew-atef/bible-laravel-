@extends('layouts.app')

@section('content')
@php 
    $lex = $summary['lexicon'];
    $cleanTranslit = $lex['clean_translit'] ?? strtolower($lex['strong_id']);
    
    // 1. تنظيف نوع الكلمة إلى لغة عربية سليمة
    $posMap = [
        'Noun Masculine' => 'اسم مذكر',
        'Noun Feminine' => 'اسم مؤنث',
        'Verb' => 'فعل',
        'Adjective' => 'صفة',
        'Preposition' => 'حرف جر',
        'Proper Name' => 'اسم علم'
    ];
    $cleanPos = $posMap[$lex['part_of_speech']] ?? ($lex['part_of_speech'] ?: 'اسم');

    // 2. تنظيف النطق الصوتي
    $cleanPron = trim(str_replace(["'", 'ʼ', 'ʻ', '`'], '', $lex['pronunciation'] ?? $lex['transliteration'] ?? ''));

    // 3. المعنى الصافي في سطر واحد
    $oneLineMeaning = $lex['short_gloss_ar'] 
        ?? $lex['short_gloss'] 
        ?? ($lex['strongs_definition'] ? explode(';', $lex['strongs_definition'])[0] : '—');
@endphp

{{-- 1. شريط BibleHub العلوي الشهير للتنقل: ◄ H410. el ► --}}
<div class="flex items-center justify-between bg-stone-100/80 border border-stone-200 rounded-2xl px-5 py-3 mb-6 shadow-xs">
    @if($prevStrong)
        <a href="{{ url('/lexicon/'.$prevStrong) }}" class="text-stone-600 hover:text-red-700 font-mono text-sm font-bold flex items-center gap-1.5 transition no-underline">
            <span>◄</span> <span>{{ $prevStrong }}</span>
        </a>
    @else
        <span class="text-stone-300 font-mono text-sm">◄ البداية</span>
    @endif

    {{-- الرقم + Transliteration بخط عريض وواضح في المنتصف --}}
    <div class="text-center">
        <span class="text-[24px] sm:text-[28px] font-black font-mono text-stone-900 tracking-tight">
            {{ $lex['strong_id'] }}. <span class="text-red-600 italic capitalize">{{ $cleanTranslit }}</span>
        </span>
    </div>

    @if($nextStrong)
        <a href="{{ url('/lexicon/'.$nextStrong) }}" class="text-stone-600 hover:text-red-700 font-mono text-sm font-bold flex items-center gap-1.5 transition no-underline">
            <span>{{ $nextStrong }}</span> <span>►</span>
        </a>
    @else
        <span class="text-stone-300 font-mono text-sm">النهاية ►</span>
    @endif
</div>

{{-- 2. بطاقة الملخص المعجمي الكاملة (حاوية واحدة نظيفة ومحكمة) --}}
<div class="bg-white rounded-[20px] border border-stone-200 overflow-hidden shadow-sm p-6 sm:p-7">
    
    {{-- عنوان السطر الواحد كملخص فوري --}}
    <div class="border-b border-stone-100 pb-4 mb-5">
        <div class="text-xs font-bold text-stone-400 uppercase tracking-wider mb-1">الملخص المعجمي • Lexical Summary</div>
        <div class="text-[20px] font-bold text-stone-900">
            <span class="text-red-600 font-mono italic">{{ $cleanTranslit }}:</span>
            <span class="text-stone-800 font-semibold">{{ $oneLineMeaning }}</span>
        </div>
    </div>

    {{-- شبكة المواصفات الأساسية (اللفظ، النوع، النطق، التكرار) --}}
    <div class="grid sm:grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        {{-- اللفظ الأصلي --}}
        <div class="p-3.5 bg-stone-50 rounded-xl border border-stone-100">
            <span class="text-stone-400 text-xs block mb-1">اللفظ الأصلي (Original)</span>
            <span class="text-2xl font-bold text-stone-900" style="font-family:var(--font-hebrew)">{{ $lex['lemma'] }}</span>
        </div>

        {{-- نوع الكلمة --}}
        <div class="p-3.5 bg-stone-50 rounded-xl border border-stone-100">
            <span class="text-stone-400 text-xs block mb-1">نوع الكلمة (Part of Speech)</span>
            <span class="font-bold text-stone-800">{{ $cleanPos }}</span>
            <span class="text-stone-400 text-xs block">({{ $lex['part_of_speech'] ?? 'Noun' }})</span>
        </div>

        {{-- النطق اللاتيني والصوتي --}}
        <div class="p-3.5 bg-stone-50 rounded-xl border border-stone-100">
            <span class="text-stone-400 text-xs block mb-1">النطق (Transliteration)</span>
            <span class="font-bold text-stone-800 font-mono text-base" dir="ltr">{{ $cleanTranslit }}</span>
            @if($cleanPron)
                <span class="text-stone-500 text-xs block font-mono">({{ $cleanPron }})</span>
            @endif
        </div>

        {{-- مرات الورود --}}
        <div class="p-3.5 bg-stone-50 rounded-xl border border-stone-100">
            <span class="text-stone-400 text-xs block mb-1">مرات الورود (Occurrences)</span>
            <span class="text-xl font-extrabold text-red-600 font-mono">{{ $summary['occurrences_count'] }}</span>
            <span class="text-stone-400 text-xs">مرة في الكتاب</span>
        </div>
    </div>

    {{-- الأصل والاشتقاق اللغوي --}}
    <div class="mt-5 p-4 rounded-xl bg-stone-50 border border-stone-100">
        <span class="text-xs font-bold text-stone-400 block mb-1.5">الأصل والاشتقاق اللغوي (Word Origin)</span>
        @if(!empty($lex['root_info']))
            <div class="flex items-center flex-wrap gap-2 text-sm text-stone-800">
                <span>مشتق من:</span>
                <a href="{{ url('/lexicon/'.$lex['root_info']['strong_id']) }}" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-white border border-stone-200 hover:border-red-300 hover:text-red-700 transition no-underline">
                    <span class="font-bold text-red-600 font-mono">{{ $lex['root_info']['strong_id'] }}</span>
                    <span class="font-bold text-stone-900 text-base" style="font-family:var(--font-hebrew)">{{ $lex['root_info']['lemma'] }}</span>
                    @if($lex['root_info']['pronunciation'])
                        <span class="text-stone-400">•</span>
                        <span class="text-stone-600 font-mono text-xs" dir="ltr">{{ $lex['root_info']['pronunciation'] }}</span>
                    @endif
                    @if($lex['root_info']['meaning_ar'])
                        <span class="text-stone-400">•</span>
                        <span class="font-bold font-arabic-text text-red-700">«{{ $lex['root_info']['meaning_ar'] }}»</span>
                    @endif
                    @if($lex['root_info']['meaning_en'])
                        <span class="text-stone-400">•</span>
                        <span class="text-stone-500 text-xs">({{ $lex['root_info']['meaning_en'] }})</span>
                    @endif
                </a>
            </div>
        @else
            <p class="text-sm text-stone-700 leading-relaxed">
                {{ !empty($lex['word_origin']) && !str_contains($lex['word_origin'], 'from the same as ;') ? $lex['word_origin'] : 'جذر لغوي أصيل (Primitive Root)' }}
            </p>
        @endif
    </div>

    {{-- معنى ودلالة اللفظ — كل معنى في سطر مرقم --}}
    @php
        $defText = $lex['strongs_definition_ar'] ?? $lex['strongs_definition'] ?? '';
        $senses = array_values(array_filter(array_map('trim', explode(';', $defText))));
    @endphp
    <div class="mt-5 p-5 rounded-2xl bg-stone-50/80 border border-stone-200/80">
        <span class="text-xs font-bold text-stone-400 block mb-2">معنى ودلالة اللفظ • Meaning & Definition</span>
        @if(count($senses) > 1)
            <div class="space-y-2.5">
                @foreach($senses as $idx => $sense)
                    <div class="flex gap-3 p-2.5 rounded-xl bg-white border border-stone-200">
                        <span class="shrink-0 w-6 h-6 grid place-items-center rounded-full bg-red-600 text-white text-[11px] font-bold">{{ $idx + 1 }}</span>
                        <span class="text-[14px] leading-[1.7] font-medium text-stone-800">{{ $sense }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-[16px] leading-[1.85] font-semibold text-stone-800">
                {{ $senses[0] ?? '—' }}
            </p>
        @endif
    </div>

    {{-- الترجمات الإنجليزية الشائعة — Inline Chips --}}
    @if(!empty($lex['kjv_translations']))
        @php
            $cleanKjv = trim(str_replace([':--', '--'], '', $lex['kjv_translations']));
            $kjvWords = array_values(array_filter(array_map('trim', explode(',', $cleanKjv))));
        @endphp
        <div class="mt-5 p-4 rounded-xl bg-stone-50 border border-stone-100">
            <span class="text-xs font-bold text-stone-400 block mb-2.5">الترجمات الإنجليزية الشائعة • KJV Translation Tags</span>
            <div class="flex flex-wrap gap-1.5" dir="ltr">
                @foreach($kjvWords as $kw)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-white border border-stone-200 text-xs font-medium text-stone-700 shadow-2xs hover:border-stone-300">
                        {{ $kw }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

</div>

{{-- 3. أشهر ترجمات الكلمة في الفانديك --}}
<div class="mt-8">
    <h2 class="text-base font-extrabold text-stone-900 mb-3">أشهر ترجمات الكلمة في ترجمة سميث وفان دايك</h2>
    <div class="flex flex-wrap gap-2.5">
        @foreach($summary['top_arabic_translations'] as $t)
            <a href="{{ route('search.query', ['q' => $t['arabic_clean']]) }}" class="inline-flex items-center gap-2 bg-white border border-stone-200 px-3.5 py-2 rounded-xl hover:border-red-300 transition no-underline shadow-xs">
                <span class="text-[17px] font-bold font-arabic-text text-stone-900">{{ $t['arabic_word'] }}</span>
                <span class="px-2 py-0.5 rounded-full bg-red-50 text-red-700 font-bold text-xs font-mono">{{ $t['count'] }}×</span>
            </a>
        @endforeach
    </div>
</div>

{{-- 4. صيغ ورود اللفظ في النص الأصلي (Forms in Text) --}}
@if(!empty($summary['hebrew_forms']))
<div class="mt-6">
    <h3 class="text-xs font-bold text-stone-500 mb-2.5">صيغ ورود اللفظ في النص الأصلي (Forms in Text):</h3>
    <div class="flex flex-wrap gap-2" dir="ltr">
        @foreach($summary['hebrew_forms'] as $form)
            <span class="px-3 py-1 rounded-lg bg-white border border-stone-200 text-sm font-bold text-stone-800 shadow-xs" style="font-family:var(--font-hebrew)">
                {{ $form['word'] }} <span class="text-stone-400 font-normal text-xs font-mono">({{ $form['count'] }}×)</span>
            </span>
        @endforeach
    </div>
</div>
@endif

{{-- 5. جدول الشواهد بالترتيب الكنسي الصحيح --}}
<div class="mt-8">
    <div class="flex items-baseline justify-between mb-4">
        <h2 class="text-base font-extrabold text-stone-900">شواهد ورود الكلمة في الكتاب المقدس ({{ $occurrences->total() }} آية)</h2>
        <span class="text-xs text-stone-400">فهرس المتوافقات الكامل (مرتب كنسياً)</span>
    </div>
    
    <div class="space-y-3">
        @foreach($occurrences as $occ)
            <div class="bg-white rounded-xl border border-stone-200 p-4 hover:border-stone-300 transition shadow-xs">
                <div class="flex items-center justify-between text-xs mb-2">
                    <a href="{{ url('/verse/'.$occ['verse_osis']) }}" class="font-bold text-red-700 hover:underline">
                        {{ $occ['book']['name_ar'] ?? '' }} {{ $occ['verse']['chapter'] ?? '' }}: {{ $occ['verse']['verse'] ?? '' }}
                    </a>
                    <span class="text-stone-400 font-mono">{{ $occ['verse_osis'] }}</span>
                </div>
                <div class="font-arabic-text text-[18px] leading-relaxed text-stone-900 mb-2">
                    {!! $occ['arabic_context_highlighted'] !!}
                </div>
                <div class="text-sm text-stone-500 font-mono" dir="ltr" style="font-family:var(--font-hebrew)">
                    {!! $occ['source_context_highlighted'] !!}
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 flex justify-center">
        {{ $occurrences->links() }}
    </div>
</div>
@endsection
