<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    {{-- SEO --}}
    <title>{{ $seo['title'] ?? config('app.name','المنصة الكتابية') }}</title>
    <meta name="description" content="{{ $seo['description'] ?? 'دراسة كتابية عربية متعمقة — 382k كلمة مُحللة' }}">
    <link rel="canonical" href="{{ $seo['canonical'] ?? url()->current() }}">
    {{-- Staged Indexation Gatekeeper: dynamic robots (Month 1 ~40 pages) --}}
    @php
        $gatekeeperEntity = $currentEntity ?? $seo['currentEntity'] ?? null;
        // Homepage (/) is always indexable; if no entity and request is '/', treat as homepage
        if ($gatekeeperEntity === null && request()->is('/')) {
            $gatekeeperEntity = '/';
        }
    @endphp
    {!! app(\App\Services\Biblical\SeoGatekeeperService::class)->renderRobotsMeta($gatekeeperEntity) !!}
    @if(isset($seo['og']))
        <meta property="og:title" content="{{ $seo['og']['title'] ?? $seo['title'] ?? '' }}">
        <meta property="og:description" content="{{ $seo['og']['description'] ?? $seo['description'] ?? '' }}">
        <meta property="og:url" content="{{ $seo['og']['url'] ?? $seo['canonical'] ?? '' }}">
        <meta property="og:type" content="{{ $seo['og']['type'] ?? 'website' }}">
        <meta property="og:locale" content="{{ $seo['og']['locale'] ?? 'ar_AR' }}">
        <meta property="og:site_name" content="{{ $seo['og']['site_name'] ?? config('app.name') }}">
        @if(!empty($seo['og']['image']))<meta property="og:image" content="{{ $seo['og']['image'] }}">@endif
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seo['og']['title'] ?? $seo['title'] ?? '' }}">
        <meta name="twitter:description" content="{{ $seo['og']['description'] ?? '' }}">
    @endif
    {{-- Tailwind CDN — فوري بدون build (يُصلح "مفيش CSS") --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={corePlugins:{preflight:false}, theme:{extend:{}}}</script>
    {{-- Vite assets (بعد npm run build) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Fonts: Cairo for UI, Amiri for vocalized Arabic, SBL Hebrew, Gentium for Greek --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Cairo:wght@400;600;700;800&family=Noto+Naskh+Arabic:wght@500;700&display=swap" rel="stylesheet">
    {{-- SBL Hebrew & Gentium via bunny/ google fallbacks --}}
    <link href="https://fonts.googleapis.com/css2?family=Gentium+Book+Plus:ital,wght@0,400;0,700;1,400&family=Scheherazade+New:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root{ --font-arabic-ui:'Cairo',system-ui,-apple-system,BlinkMacSystemFont,sans-serif; --font-arabic-text:'Amiri','Noto Naskh Arabic',serif; --font-hebrew:'SBL Hebrew','Scheherazade New',serif; --font-greek:'Gentium Book Plus',serif; }
        html{scroll-behavior:smooth}
        body{font-family:var(--font-arabic-ui); background:#fafaf9; color:#1c1917; -webkit-font-smoothing:antialiased; text-rendering:optimizeLegibility}
        .font-arabic-text{font-family:var(--font-arabic-text)}
        .font-hebrew{font-family:var(--font-hebrew)}
        .font-greek{font-family:var(--font-greek)}
        /* Apple Books-like paragraph */
        .scripture-paragraph{font-family:var(--font-arabic-text); font-size:22px; line-height:2.15; letter-spacing:0; word-spacing:.06em}
        @media(max-width:640px){ .scripture-paragraph{font-size:19px; line-height:2.05} }
        .word-token{cursor:pointer; padding:1px 3px; border-radius:6px; transition:background .15s, box-shadow .15s; border-bottom:1px solid transparent}
        .word-token:hover{background:#fff1f2; border-bottom-color:#fecdd3}
        .word-token.active{background:#ffe4e6; border-bottom-color:#fda4af; box-shadow:0 1px 0 #fda4af}
        .verse-num{font-family:var(--font-arabic-ui); font-size:11px; font-weight:700; color:#e11d48; background:#fff1f2; border:1px solid #ffe4e6; padding:2px 6px; border-radius:999px; vertical-align:middle; margin-inline-end:4px; position:relative; top:-1px}
        .verse-num:hover{background:#ffe4e6}
        /* Drawer: bottom-sheet on mobile, sidebar on desktop — zero layout shift (overlay) */
        .drawer-backdrop{position:fixed; inset:0; background:rgba(24,12,12,.28); backdrop-filter:blur(2px); opacity:0; pointer-events:none; transition:opacity .25s; z-index:40}
        .drawer-backdrop.open{opacity:1; pointer-events:auto}
        .word-drawer{position:fixed !important; z-index:50; background:#fff; border:1px solid #e7e5e4; box-shadow:0 20px 60px rgba(0,0,0,.18), 0 8px 20px rgba(0,0,0,.08); transition:transform .32s cubic-bezier(.32,.72,0,1), visibility .32s, opacity .25s; overflow:hidden; display:flex; flex-direction:column; visibility:hidden !important; opacity:0 !important; pointer-events:none !important}
        .word-drawer.open{visibility:visible !important; opacity:1 !important; pointer-events:auto !important}
        /* Mobile: bottom sheet */
        @media(max-width:768px){
          .word-drawer{inset-inline:0; bottom:0; top:auto; border-radius:20px 20px 0 0; max-height:82vh; transform:translateY(105%) !important}
          .word-drawer.open{transform:translateY(0) !important}
        }
        /* Desktop: floating sidebar */
        @media(min-width:769px){
          .word-drawer{inset-inline-end:16px; top:16px; bottom:16px; width:380px; border-radius:16px; transform:translateX(110%) !important}
          .word-drawer.open{transform:translateX(0) !important}
          :dir(ltr) .word-drawer{transform:translateX(-110%) !important}
          :dir(ltr) .word-drawer.open{transform:translateX(0) !important}
        }
        .drawer-handle{width:36px; height:4px; border-radius:999px; background:#e7e5e4; margin:10px auto 0}
        @media(min-width:769px){ .drawer-handle{display:none} }
        mark.highlight, .highlight{background:#fef08a; padding:0 .15em; border-radius:3px}
        /* Accordion */
        details.accordion{border:1px solid #e7e5e4; border-radius:12px; background:#fff; overflow:hidden}
        details.accordion + details.accordion{margin-top:10px}
        details.accordion summary{list-style:none; cursor:pointer; padding:14px 16px; display:flex; align-items:center; justify-content:space-between; font-weight:700; font-size:14px; color:#1c1917}
        details.accordion summary::-webkit-details-marker{display:none}
        details.accordion[open] summary{border-bottom:1px solid #f5f5f4; background:#fafaf9}
        .pill{padding:6px 10px; border:1px solid #e7e5e4; border-radius:999px; font-size:13px; background:#fff; cursor:pointer; transition:.15s}
        .pill:hover{border-color:#fecdd3; background:#fff1f2}
        .pill.active{background:#e11d48; color:#fff; border-color:#e11d48}
    </style>
    <!-- Alpine.js for micro-interactions -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @stack('head')
</head>
<body class="min-h-screen">
<header class="sticky top-0 z-30 bg-white/85 backdrop-blur supports-[backdrop-filter]:bg-white/75 border-b border-stone-200">
    <div class="max-w-[1120px] mx-auto px-4 h-[64px] flex items-center gap-3 justify-between">
        <a href="{{ url('/') }}" class="flex items-center gap-2.5 no-underline shrink-0">
            <span class="w-9 h-9 rounded-xl bg-red-600 text-white grid place-items-center font-extrabold text-[15px]">ك</span>
            <span class="hidden sm:block font-extrabold text-stone-800 tracking-tight">{{ config('app.name','المنصة الكتابية') }}</span>
        </a>
        {{-- Book / Chapter selector + Search — visible on all devices --}}
        <div x-data="headerSelector()" class="flex items-center gap-2 flex-1 max-w-[580px] justify-end">
            <div class="flex items-center gap-1.5">
                <select x-model="book" @change="goBook()" class="h-9 rounded-full border border-stone-200 bg-stone-50 px-2 sm:px-3 text-[12px] sm:text-[13px] font-semibold text-stone-700 focus:outline-none focus:ring-2 focus:ring-red-100 max-w-[120px] sm:max-w-[180px]">
                    <option value="">السفر…</option>
                    @isset($navBooks)
                        @foreach($navBooks as $b)
                            <option value="{{ $b->slug }}">{{ $b->name_ar }}</option>
                        @endforeach
                    @endisset
                </select>
                <!-- Chapter Grid Picker — 4 per row -->
                <div class="relative">
                    <button @click="showChapters = !showChapters" :disabled="!book" :class="!book ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'" class="h-9 px-2 sm:px-3 rounded-full border border-stone-200 bg-stone-50 text-[12px] sm:text-[13px] font-semibold text-stone-700 flex items-center gap-1 min-w-[70px] sm:min-w-[86px] justify-center">
                        <span x-text="chapter ? chapter : 'إصحاح'"></span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div x-show="showChapters" x-cloak @click.outside="showChapters = false" x-transition class="absolute top-full mt-2 inset-inline-start-0 bg-white border border-stone-200 rounded-2xl shadow-[0_16px_32px_rgba(0,0,0,.12)] p-3 w-[220px] z-50">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] font-bold tracking-wide text-stone-500">إصحاح</span>
                            <button @click="showChapters=false" class="text-[11px] font-bold text-red-600 hover:text-red-700 px-2 py-1 rounded-full hover:bg-red-50">إلغاء</button>
                        </div>
                        <div class="grid grid-cols-4 gap-1.5 max-h-[200px] overflow-y-auto">
                            <template x-for="n in chapterCount" :key="n">
                                <button @click="chapter=n; showChapters=false; goChapter()" :class="chapter==n ? 'bg-stone-900 text-white border-stone-900' : 'bg-white text-stone-700 hover:bg-stone-50'" class="h-9 rounded-xl border border-stone-200 text-[13px] font-bold" x-text="n"></button>
                            </template>
                        </div>
                    </div>
                </div>
                <button @click="goChapter()" class="hidden sm:inline-flex h-9 px-3 sm:px-4 rounded-full bg-stone-900 text-white text-[12px] sm:text-[13px] font-bold hover:bg-black transition">اذهب</button>
            </div>
            <form action="{{ route('search.query') }}" method="GET" class="flex items-center gap-1.5 flex-1 sm:flex-none md:w-[280px]">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 start-3 grid place-items-center text-stone-400">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20 L16.5 16.5"/></svg>
                    </span>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="ابحث: كلمة، H7225، Logos" class="w-full h-9 ps-9 pe-3 rounded-full border border-stone-200 bg-stone-50 text-[13px] placeholder:text-stone-400 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-200">
                </div>
                <button type="submit" class="hidden sm:inline-flex h-9 px-4 rounded-full bg-red-600 text-white text-[13px] font-bold hover:bg-red-700 transition">بحث</button>
            </form>
        </div>
    </div>
</header>
@if(!empty($breadcrumbs))
<nav aria-label="Breadcrumb" class="max-w-[1120px] mx-auto px-4 py-3 text-[13px] text-stone-500 flex items-center gap-1.5 flex-wrap">
    @foreach($breadcrumbs as $crumb)
        @if(!$loop->last)
            <a href="{{ $crumb['url'] }}" class="hover:text-red-600 no-underline">{{ $crumb['name'] }}</a><span class="text-stone-300">›</span>
        @else
            <span class="text-stone-700 font-semibold">{{ $crumb['name'] }}</span>
        @endif
    @endforeach
</nav>
@endif
<main class="max-w-[1120px] mx-auto px-4 py-6 sm:py-8">
    @yield('content')
</main>
<footer class="border-t border-stone-200 mt-12 py-8 text-center text-[13px] text-stone-500">
    <div class="max-w-[1120px] mx-auto px-4">
        <p>المنصة الكتابية العربية · 382,535 كلمة مُحللة</p>
        <p class="mt-1 text-stone-400">دراسة كتابية عميقة للنص الأصلي</p>
    </div>
</footer>
@if(!empty($seo['jsonLd']))
<script type="application/ld+json">{!! json_encode($seo['jsonLd'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
@endif
@if(!empty($seo['breadcrumbLd']))
<script type="application/ld+json">{!! json_encode($seo['breadcrumbLd'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
@endif
@if(!empty($seo['jsonLdArticle']))
<script type="application/ld+json">{!! json_encode($seo['jsonLdArticle'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
@endif
<script>
function headerSelector(){
  const chapterCounts = {
    'al-takwin':50,'al-khuruj':40,'al-lawyin':27,'al-adad':36,'al-tathniya':34,'yashua':24,'al-qudat':21,'raouth':4,'samuel-al-awwal':31,'samuel-al-thani':24,'al-muluk-al-awwal':22,'al-muluk-al-thani':25,'akhbar-al-ayyam-al-awwal':29,'akhbar-al-ayyam-al-thani':36,'azra':10,'nahmya':13,'astir':10,'ayyub':42,'al-mazamir':150,'al-amthal':31,'al-jamia':12,'nashid-al-anshad':8,'ishaia':66,'irmya':52,'marathi-irmya':5,'hazqyal':48,'danyal':12,'hushai':14,'yuil':3,'amous':9,'oubadya':1,'yunan':4,'mikha':7,'nahum':3,'habquq':3,'safanya':3,'haji':2,'zakariya':14,'malakhi':4,'matta':28,'murqus':16,'luqa':24,'yuhanna':21,'aamal-al-rusul':28,'rumya':16,'kurunthus-al-ula':16,'kurunthus-al-thaniya':13,'ghalatya':6,'afsas':6,'filibbi':4,'kulussi':4,'tasaluniki-al-ula':5,'tasaluniki-al-thaniya':3,'timuthawus-al-ula':6,'timuthawus-al-thaniya':4,'titus':3,'filimun':1,'al-ibranyin':13,'yaqub':5,'butrus-al-ula':5,'butrus-al-thaniya':3,'yuhanna-al-ula':5,'yuhanna-al-thaniya':1,'yuhanna-al-thalitha':1,'yahutha':1,'al-ruya':22,
    'gen':50,'exo':40,'lev':27,'num':36,'deu':34
  };
  return {
    book: '{{ request()->segment(2) ?? '' }}',
    chapter: '{{ request()->segment(3) ?? '' }}',
    showChapters: false,
    get chapterCount(){
      return chapterCounts[this.book] || 50;
    },
    goBook(){
      if(!this.book) return;
      const ch = this.chapter || '1';
      window.location.href = `/read/${this.book}/${ch}`;
    },
    goChapter(){
      if(!this.book || !this.chapter) return;
      window.location.href = `/read/${this.book}/${this.chapter}`;
    }
  }
}
</script>
@stack('scripts')
</body>
</html>
