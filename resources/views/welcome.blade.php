@extends('layouts.app')
@section('content')
{{-- Hero — YouVersion / Apple Books inspired --}}
<div class="bg-white rounded-[24px] border border-stone-200 p-6 sm:p-8 shadow-[0_16px_40px_rgba(0,0,0,.06)]">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex-1 min-w-[280px]">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-50 border border-red-100 text-[11px] font-bold tracking-wide text-red-600">المنصة الكتابية العربية</div>
            <h1 class="mt-3 text-[30px] sm:text-[36px] font-extrabold leading-[1.1] tracking-tight text-stone-900">
                اقرأ الكتاب<br>
                <span class="font-arabic-text font-normal text-stone-500">كلمة بكلمة مع الأصل</span>
            </h1>
            <p class="mt-3 text-[14px] leading-[1.8] text-stone-600 max-w-[560px]">382,535 كلمة مُحللة — كل كلمة عربية مرتبطة بأصلها العبري/اليوناني، النطق، والتحليل النحوي. دراسة عميقة ونقية.</p>
            <form action="{{ route('search.query') }}" method="GET" class="mt-5 flex gap-2 max-w-[560px]">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 start-3 grid place-items-center text-stone-400">⌕</span>
                    <input type="text" name="q" placeholder="ابحث: في البدء، H7225، Logos، يوحنا 3:16" class="w-full h-[44px] ps-10 pe-4 rounded-full border border-stone-200 bg-stone-50 text-[14px] placeholder:text-stone-400 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-200">
                </div>
                <button class="h-[44px] px-6 rounded-full bg-red-600 text-white text-[14px] font-bold hover:bg-red-700">بحث</button>
            </form>
            <div class="mt-3 flex flex-wrap gap-1.5">
                <a href="{{ url('/read/al-takwin/1') }}" class="px-3 py-1.5 rounded-full bg-stone-900 text-white text-[12px] font-bold hover:bg-black">ابدأ من التكوين 1 →</a>
                <a href="{{ url('/read/yuhanna/1') }}" class="px-3 py-1.5 rounded-full border border-stone-200 bg-white text-[12px] font-semibold text-stone-700 hover:bg-stone-50">يوحنا 1 — في البدء كان الكلمة</a>
                <a href="{{ url('/lexicon/H7225') }}" class="px-3 py-1.5 rounded-full border border-stone-200 bg-white text-[12px] font-semibold text-stone-700">H7225 رِئشيت</a>
            </div>
        </div>
        <div class="hidden lg:block w-[320px] shrink-0">
            <div class="rounded-2xl bg-stone-900 text-stone-100 p-5">
                <div class="text-[11px] font-bold tracking-wide text-stone-400">آية اليوم — دراسة فورية</div>
                <div class="mt-2 font-arabic-text text-[18px] leading-[1.9]">فِي الْبَدْءِ خَلَقَ اللهُ السَّمَاوَاتِ وَالأَرْضَ.</div>
                <div class="mt-1 font-hebrew text-[12px] text-stone-400" dir="ltr">בְּרֵאשִׁית בָּרָא אֱלֹהִים אֵת הַשָּׁמַיִם וְאֵת הָאָרֶץ</div>
                <div class="mt-3 flex gap-2">
                    <a href="{{ url('/verse/Gen.1.1') }}" class="flex-1 h-9 grid place-items-center rounded-full bg-white text-stone-900 text-[12px] font-bold">دراسة Gen.1.1</a>
                    <a href="{{ url('/lexicon/H7225') }}" class="flex-1 h-9 grid place-items-center rounded-full border border-stone-700 text-stone-200 text-[12px] font-bold">H7225</a>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Testament Books Grid --}}
@php $ot = $navBooks->where('testament','OT'); $nt = $navBooks->where('testament','NT'); @endphp
<div class="mt-8">
    <h2 class="text-[13px] font-bold tracking-wide text-stone-500">العهد القديم — 39 سفر</h2>
    <div class="mt-3 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2">
        @foreach($ot as $b)
            <a href="{{ url('/read/'.$b->slug.'/1') }}" class="group p-3 rounded-2xl border border-stone-200 bg-white hover:border-red-200 hover:shadow-[0_8px_20px_rgba(0,0,0,.06)] transition text-center">
                <div class="text-[13px] font-bold text-stone-900 group-hover:text-red-600">{{ $b->name_ar }}</div>
            </a>
        @endforeach
    </div>
</div>
<div class="mt-8">
    <h2 class="text-[13px] font-bold tracking-wide text-stone-500">العهد الجديد — 27 سفر</h2>
    <div class="mt-3 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2">
        @foreach($nt as $b)
            <a href="{{ url('/read/'.$b->slug.'/1') }}" class="group p-3 rounded-2xl border border-stone-200 bg-white hover:border-red-200 hover:shadow-[0_8px_20px_rgba(0,0,0,.06)] transition text-center">
                <div class="text-[13px] font-bold text-stone-900 group-hover:text-red-600">{{ $b->name_ar }}</div>
            </a>
        @endforeach
    </div>
</div>

<div class="mt-8 grid md:grid-cols-3 gap-3">
    <a href="{{ url('/read/al-takwin/1') }}" class="p-4 rounded-2xl bg-white border border-stone-200 hover:border-stone-300">
        <div class="text-[12px] font-bold tracking-wide text-red-600">ابدأ القراءة</div>
        <div class="mt-1 font-bold text-stone-900">التكوين 1 — الخلق</div>
        <div class="text-[12px] text-stone-500">31 آية مع الأصل العبري</div>
    </a>
    <a href="{{ url('/read/yuhanna/1') }}" class="p-4 rounded-2xl bg-stone-900 text-white hover:bg-black">
        <div class="text-[12px] font-bold tracking-wide text-red-300">الأكثر دراسة</div>
        <div class="mt-1 font-bold">يوحنا 1 — في البدء كان الكلمة</div>
        <div class="text-[12px] text-stone-400">51 آية + تفاسير ذهبي الفم والمسكين</div>
    </a>
    <a href="{{ url('/lexicon/H7225') }}" class="p-4 rounded-2xl bg-white border border-stone-200 hover:border-stone-300">
        <div class="text-[12px] font-bold tracking-wide text-stone-500">معجم سترونغ</div>
        <div class="mt-1 font-bold text-stone-900" style="font-family:var(--font-hebrew)">רֵאשִׁית H7225</div>
        <div class="text-[12px] text-stone-500">في البدء — 51 موضع</div>
    </a>
</div>
@endsection
