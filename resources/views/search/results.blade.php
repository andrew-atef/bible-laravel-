{{-- resources/views/search/results.blade.php — grouped: معجم الكلمات + شواهد الآيات --}}
@extends('layouts.app')
@section('content')
<div class="max-w-[900px] mx-auto">
    <h1 class="text-[22px] font-extrabold text-stone-900">البحث الذكي</h1>
    <p class="text-[13px] text-stone-500 mt-1">ابحث بالشاهد (تكوين 1:1، Gen 1:1، يوحنا 3:16) أو بالنص العربي مع/بدون تشكيل، أو برقم سترونغ (H7225)، أو بالنطق (Tohu, Logos)</p>

    <form action="{{ route('search.query') }}" method="GET" class="mt-4 flex gap-2">
        <div class="relative flex-1">
            <span class="absolute inset-y-0 start-3 grid place-items-center text-stone-400">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20 L16.5 16.5"/></svg>
            </span>
            <input type="text" name="q" value="{{ $q }}" placeholder="تكوين 1:1، في البدء، H7225، Bereshit..." class="w-full h-11 ps-10 pe-4 rounded-full border border-stone-200 bg-white text-[14px] placeholder:text-stone-400 focus:outline-none focus:ring-2 focus:ring-red-100">
        </div>
        <button class="h-11 px-6 rounded-full bg-red-600 text-white text-[14px] font-bold hover:bg-red-700">بحث</button>
    </form>
    <div class="mt-2 flex gap-1.5 flex-wrap text-[11px] text-stone-500">
        <span>جرّب:</span>
        <a href="{{ route('search.query',['q'=>'تكوين 1']) }}" class="px-2 py-1 rounded-full border border-stone-200 hover:bg-stone-50">تكوين 1</a>
        <a href="{{ route('search.query',['q'=>'Gen 1:1']) }}" class="px-2 py-1 rounded-full border border-stone-200 hover:bg-stone-50">Gen 1:1</a>
        <a href="{{ route('search.query',['q'=>'يوحنا 1:1']) }}" class="px-2 py-1 rounded-full border border-stone-200 hover:bg-stone-50">يوحنا 1:1</a>
        <a href="{{ route('search.query',['q'=>'بدء']) }}" class="px-2 py-1 rounded-full border border-stone-200 hover:bg-stone-50">بدء</a>
        <a href="{{ route('search.query',['q'=>'H7225']) }}" class="px-2 py-1 rounded-full border border-stone-200 hover:bg-stone-50">H7225</a>
        <a href="{{ route('search.query',['q'=>'Logos']) }}" class="px-2 py-1 rounded-full border border-stone-200 hover:bg-stone-50">Logos</a>
    </div>

    @if($q === '')
        <div class="mt-8 p-8 text-center border border-dashed border-stone-200 rounded-2xl bg-stone-50">
            <div class="w-12 h-12 mx-auto grid place-items-center rounded-full bg-white border border-stone-200 text-stone-400">⌕</div>
            <p class="mt-3 text-[14px] font-semibold text-stone-700">أدخل كلمة للبحث</p>
            <p class="text-[13px] text-stone-500">النص العربي يتجاهل التشكيل واختلاف الألف/الياء تلقائياً</p>
        </div>
    @else
        <div class="mt-6 flex items-center gap-2 text-[13px] text-stone-500 border-b border-stone-100 pb-3">
            <span>وضع البحث: <strong class="text-stone-700">{{ $mode }}</strong></span>
            <span>•</span>
            <span>{{ $total ?? ($verses->total() ?? $results->total() ?? 0) }} نتيجة لـ “{{ $q }}”</span>
            @if(isset($lexicons) && $lexicons->count())<span class="ms-auto text-[11px] px-2 py-1 rounded-full bg-red-50 border border-red-100 text-red-700">{{ $lexicons->count() }} معجم</span>@endif
        </div>

        {{-- Group 1: معجم الكلمات --}}
        @if(isset($lexicons) && $lexicons->count())
        <section class="mt-6">
            <h2 class="text-[15px] font-extrabold text-stone-900 flex items-center gap-2">
                <span class="w-7 h-7 grid place-items-center rounded-full bg-stone-900 text-white text-[11px] font-bold">1</span>
                معجم الكلمات
                <span class="text-[12px] font-normal text-stone-500">— سترونغ، الأصل، النطق</span>
            </h2>
            <div class="mt-3 grid gap-2">
                @foreach($lexicons as $lx)
                    <a href="{{ url('/lexicon/'.$lx->strong_id) }}" class="group flex items-center gap-3 p-3 rounded-2xl border border-stone-200 bg-white hover:border-red-200 hover:shadow-[0_8px_24px_rgba(0,0,0,.06)] transition text-right">
                        <span class="shrink-0 px-2.5 py-1 rounded-full bg-red-600 text-white text-[12px] font-bold">{{ $lx->strong_id }}</span>
                        <span class="font-bold text-stone-900" style="font-family:var(--font-hebrew)">{{ $lx->lemma }}</span>
                        <span class="text-stone-500 font-greek text-[13px]">{{ $lx->transliteration }}</span>
                        <span class="hidden sm:inline text-[12px] text-stone-400 truncate">{{ \Illuminate\Support\Str::limit($lx->strongs_definition, 70) }}</span>
                        <span class="ms-auto text-stone-300 group-hover:text-red-500">→</span>
                    </a>
                @endforeach
            </div>
        </section>
        @endif

        {{-- Group 2: شواهد الآيات — عرض نص الآية الكامل مع تظليل --}}
        <section class="mt-8">
            <h2 class="text-[15px] font-extrabold text-stone-900 flex items-center gap-2">
                <span class="w-7 h-7 grid place-items-center rounded-full bg-red-600 text-white text-[11px] font-bold">2</span>
                شواهد الآيات
                <span class="text-[12px] font-normal text-stone-500">— نص الآية الكامل مع تظليل العبارة</span>
            </h2>
            @php $paginator = $verses ?? $results; @endphp
            @if($paginator && $paginator->count())
                <div class="mt-3 grid gap-3">
                    @foreach($paginator as $v)
                        <div class="bg-white rounded-xl border border-stone-200 p-4 hover:border-red-200 transition">
                            <div class="flex items-center justify-between text-xs mb-2">
                                <a href="{{ url('/verse/'.$v->osis_id) }}" class="font-bold text-red-700 hover:underline">
                                    {{ $v->book->name_ar ?? '' }} {{ $v->chapter }}:{{ $v->verse }}
                                </a>
                                <span class="text-stone-400 font-mono">{{ $v->osis_id }}</span>
                            </div>
                            {{-- عرض نص الآية مع تظليل العبارة المطلوبة --}}
                            <div class="font-arabic-text text-[18px] leading-relaxed text-stone-900">
                                {!! app(\App\Services\Biblical\ArabicScriptService::class)->highlightToken($v->text_ar, $q) !!}
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-6 flex justify-center">
                    {{ $paginator->withQueryString()->onEachSide(1)->links() }}
                </div>
            @else
                <div class="mt-3 p-6 text-center border border-dashed border-stone-200 rounded-2xl text-stone-500 text-[13px]">لا توجد شواهد لهذا البحث — جرّب بدون تشكيل أو برقم سترونغ</div>
            @endif
        </section>
    @endif
</div>
@endsection
