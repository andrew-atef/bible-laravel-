{{-- resources/views/bible/reader.blade.php — Modern RTL paragraphs, YouVersion/Apple Books inspired --}}
@extends('layouts.app')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div>
        <h1 class="text-[28px] sm:text-[30px] font-extrabold tracking-tight leading-none text-stone-900">
            {{ $bookModel->name_ar }} <span class="font-normal text-stone-400 text-[18px]">— الإصحاح {{ $chapter }}</span>
        </h1>
        <p class="mt-1 text-[13px] text-stone-500">{{ $bookModel->name_en }} · {{ $bookModel->code }}.{{ $chapter }} · {{ $verses->count() }} آية · الفانديك مع الأصل</p>
    </div>
    <div class="flex items-center gap-2">
        @if($prevChapter)
            <a href="{{ url('/read/'.$prevChapter['book_slug'].'/'.$prevChapter['chapter']) }}" class="inline-flex items-center gap-1 h-9 px-3.5 rounded-full border border-stone-200 bg-white text-[13px] font-semibold text-stone-700 hover:bg-stone-50">‹ <span class="hidden sm:inline">{{ $prevChapter['book_name_ar'] }}</span> {{ $prevChapter['chapter'] }}</a>
        @else
            <span class="h-9 px-3.5 rounded-full border border-stone-100 bg-stone-50 text-[13px] text-stone-400 inline-flex items-center">البداية</span>
        @endif
        @if($nextChapter)
            <a href="{{ url('/read/'.$nextChapter['book_slug'].'/'.$nextChapter['chapter']) }}" class="inline-flex items-center gap-1 h-9 px-3.5 rounded-full bg-stone-900 text-white text-[13px] font-bold hover:bg-black">{{ $nextChapter['book_name_ar'] }} {{ $nextChapter['chapter'] }} ›</a>
        @endif
    </div>
</div>

{{-- Scripture as beautiful flowing paragraphs (group 5 verses per paragraph like Apple Books) --}}
<div class="bg-white rounded-[20px] border border-stone-200 p-5 sm:p-8 shadow-[0_10px_30px_rgba(0,0,0,.04)]">
    <div class="scripture-paragraph" id="scripture-content">
        @foreach($verses as $verse)
            <span class="inline" data-osis="{{ $verse->osis_id }}" data-verse="{{ $verse->verse }}" data-chapter="{{ $verse->chapter }}">
                <a href="{{ url('/verse/'.$verse->osis_id) }}" class="verse-num" title="افتح دراسة {{ $verse->osis_id }}">﴿{{ $verse->verse }}﴾</a>
                @foreach($verse->tokens as $tok)
                    <span class="word-token"
                          data-strong="{{ $tok->strong_id }}"
                          data-arabic="{{ $tok->arabic_word }}"
                          data-source="{{ $tok->source_word }}"
                          data-translit="{{ $tok->lexicon->transliteration ?? $tok->transliteration }}"
                          data-pronunciation="{{ $tok->lexicon->pronunciation ?? '' }}"
                          data-gloss-ar=""
                          data-gloss-en="{{ $tok->gloss_en }}"
                          data-gloss="{{ $tok->gloss_en }}"
                          data-morph-ar="{{ $tok->morphology_ar }}"
                          data-lemma="{{ $tok->lexicon->lemma ?? '' }}"
                          data-lemma-clean="{{ $tok->lexicon->lemma_clean ?? '' }}"
                          data-lang="{{ $tok->lexicon->language ?? '' }}"
                          role="button" tabindex="0"
                          aria-label="دراسة {{ $tok->arabic_word }} {{ $tok->strong_id }}"
                          onclick="openWordDrawer(this)"
                          onkeydown="if(event.key==='Enter') openWordDrawer(this)">
                        {{ $tok->arabic_word }}</span>@if(!$loop->last) <span class="text-stone-200 select-none"> </span> @endif
                @endforeach
            </span>
            @if($loop->iteration % 3 === 0)
                <span class="block h-3"></span>
            @endif
        @endforeach
    </div>
    <div class="mt-6 pt-4 border-t border-stone-100 flex flex-wrap gap-2 text-[12px] text-stone-500">
        <span>اضغط أي كلمة لفتح الدراسة •</span>
        <span>مرّر للقراءة السلسة بدون فواصل مصطنعة</span>
    </div>
</div>

@if($commentaries && $commentaries->count())
<div class="mt-8">
    <h2 class="text-[15px] font-bold text-stone-900 mb-3">تفاسير مختارة لهذا الإصحاح</h2>
    <div class="grid gap-3">
        @foreach($commentaries as $c)
            <details class="accordion">
                <summary>
                    <span>{{ $c->title ?? $c->verse_start }} <span class="font-normal text-stone-500">— {{ $c->author_name }} · {{ $c->level==='word'?'كلمة':($c->level==='verse'?'آية':'مقطع') }}</span></span>
                    <span class="w-7 h-7 grid place-items-center rounded-full bg-stone-100 text-stone-500">⌄</span>
                </summary>
                <div class="p-4 text-[14px] leading-[1.9] text-stone-700">{!! $c->body_html !!}</div>
            </details>
        @endforeach
    </div>
</div>
@endif

{{-- Word Drawer النظيف المبسط للمستخدم العادي --}}
<div id="drawer-backdrop" class="drawer-backdrop" onclick="closeWordDrawer()" aria-hidden="true"></div>
<aside id="word-drawer" class="word-drawer" role="dialog" aria-modal="true">
    <div class="drawer-handle"></div>
    
    {{-- ترويسة البطاقة: الكلمة العربية التي ضغط عليها القارئ هي البطل --}}
    <div class="px-5 pt-4 pb-3 border-b border-stone-100 flex items-center justify-between">
        <div>
            <h2 id="drawer-arabic-title" class="text-[22px] font-extrabold text-stone-900 font-arabic-text"></h2>
            <div class="flex items-center gap-2 mt-1 flex-wrap">
                <span id="drawer-strong-badge" class="px-2 py-0.5 rounded-full bg-red-50 text-red-700 font-bold text-[11px] font-mono"></span>
                <span id="drawer-translit-header" class="text-[15px] font-bold font-mono text-stone-800 tracking-tight italic" dir="ltr"></span>
                <span class="text-stone-300">•</span>
                <span id="drawer-morph-simple" class="text-[12px] font-semibold text-stone-600"></span>
            </div>
        </div>
        <button onclick="closeWordDrawer()" class="w-8 h-8 rounded-full bg-stone-100 text-stone-500 hover:bg-stone-200 flex items-center justify-center font-bold text-sm">✕</button>
    </div>

    <div class="p-5 space-y-4 overflow-y-auto">
        {{-- الأصل العبري/اليوناني ونطقه الصوتي فقط --}}
        <div class="p-3.5 rounded-xl bg-stone-50 border border-stone-100 flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-stone-400 block mb-1">اللفظ الأصلي</span>
                <span id="drawer-source-word" class="text-[24px] font-bold text-stone-900 leading-none" style="font-family:var(--font-hebrew)"></span>
            </div>
            <div class="text-end">
                <span class="text-[11px] font-bold text-stone-400 block mb-1">طريقة النطق</span>
                <span id="drawer-phonetic" class="text-[14px] font-semibold text-stone-700 font-mono" dir="ltr"></span>
            </div>
        </div>

        {{-- المعنى البسيط النظيف بدون أقواس معقوفة أو فواصل غريبة --}}
        <div>
            <span class="text-[11px] font-bold text-stone-400 block mb-1">المعنى في السياق</span>
            <div id="drawer-clean-meaning" class="text-[15px] font-semibold text-stone-800 leading-relaxed bg-red-50/50 p-3 rounded-xl border border-red-100/60"></div>
        </div>

        {{-- الأزرار المباشرة --}}
        <div class="pt-2 flex flex-col gap-2">
            <a id="drawer-lexicon-link" href="#" class="w-full flex items-center justify-center h-11 rounded-full bg-red-600 text-white text-[13px] font-bold hover:bg-red-700 transition no-underline">
                عرض دراسة الكلمة وشواهدها بالكامل →
            </a>
            <a id="drawer-verse-link" href="#" class="w-full flex items-center justify-center h-10 rounded-full border border-stone-200 bg-white text-[13px] font-semibold text-stone-700 hover:bg-stone-50 transition no-underline">
                دراسة الآية كاملة
            </a>
        </div>
    </div>
</aside>

@push('scripts')
<script>
// تبسيط النطق الصوتي (مثل BibleHub تماماً: نطق واحد نظيف)
function cleanPronunciation(pron, translit) {
    let p = pron || translit || '';
    return p.replace(/[ʻʼ]/g, '').trim(); // حذف الرموز الصوتية المنفرة
}

function openWordDrawer(el) {
    const d = document.getElementById('word-drawer');
    const b = document.getElementById('drawer-backdrop');
    
    const strong = el.dataset.strong || '';
    const arabic = el.dataset.arabic || '';
    const source = el.dataset.source || '';
    const translit = el.dataset.translit || '';
    const cleanTr = translit.replace(/[ʻʼ']/g, '').trim();
    const pron = el.dataset.pronunciation || '';
    const glossAr = el.dataset.glossAr || '';
    const glossEn = el.dataset.glossEn || el.dataset.gloss || '';
    const arabicWord = el.dataset.arabic || '';
    const morphAr = el.dataset.morphAr || '';
    const osis = el.closest('[data-osis]')?.getAttribute('data-osis') || el.closest('[id]')?.id || '';

    // تعبئة الحقول النظيفة فقط — Golden Rule: read directly from DB, no JS regex
    document.getElementById('drawer-arabic-title').textContent = arabic;
    document.getElementById('drawer-strong-badge').textContent = strong;
    const translitHeader = document.getElementById('drawer-translit-header');
    if (translitHeader) translitHeader.textContent = cleanTr;
    document.getElementById('drawer-morph-simple').textContent = morphAr || '—';
    document.getElementById('drawer-source-word').textContent = source;
    document.getElementById('drawer-phonetic').textContent = cleanPronunciation(pron, translit);
    const cleanMeaningEl = document.getElementById('drawer-clean-meaning');
    if (glossAr && glossAr.trim() !== '') {
        cleanMeaningEl.innerHTML = `<div>${glossAr}</div>` + (glossEn ? `<div class="text-xs text-stone-400 mt-1 font-mono">${glossEn}</div>` : '');
    } else {
        cleanMeaningEl.textContent = glossEn && glossEn.trim() !== '' ? glossEn : arabicWord;
    }
    
    document.getElementById('drawer-lexicon-link').href = '/lexicon/' + strong;
    document.getElementById('drawer-verse-link').href = osis ? '/verse/' + osis : '#';

    // تمييز الكلمة المحددة وفتح البطاقة
    document.querySelectorAll('.word-token.active').forEach(x => x.classList.remove('active'));
    el.classList.add('active');
    d.classList.add('open');
    b.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeWordDrawer() {
    document.getElementById('word-drawer').classList.remove('open');
    document.getElementById('drawer-backdrop').classList.remove('open');
    document.body.style.overflow = '';
    document.querySelectorAll('.word-token.active').forEach(x => x.classList.remove('active'));
}

document.addEventListener('keydown', e => { if(e.key==='Escape') closeWordDrawer(); });

// ——— نسخ مخصص مع الشاهد ورابط المنصة ———
document.addEventListener('copy', function(e) {
  const sel = window.getSelection();
  let selectedText = sel ? sel.toString().trim() : '';
  if (!selectedText) return;
  // نظف أرقام الآيات ﴿1﴾ من النص المنسوخ
  selectedText = selectedText.replace(/﴿[^﴾]*﴾/g, '').replace(/\s+/g, ' ').trim();
  const container = document.getElementById('scripture-content');
  if (!container || !sel.rangeCount) return;
  const range = sel.getRangeAt(0);
  const verses = Array.from(container.querySelectorAll('[data-osis]'));
  const selectedVerses = verses.filter(el => {
    try { return sel.containsNode(el, true) || range.intersectsNode(el); } catch { return false; }
  });
  let refs = [];
  let firstOsis = '';
  if (selectedVerses.length > 0) {
    const h1 = document.querySelector('h1');
    let bookName2 = '';
    if(h1) { const m = h1.textContent.match(/^([^\n—]+)/); if(m) bookName2 = m[1].trim(); }
    const byVerse = selectedVerses.map(el => { const v = el.getAttribute('data-verse'); const osis = el.getAttribute('data-osis'); if(!firstOsis) firstOsis = osis; return v; });
    const uniq = [...new Set(byVerse)].sort((a,b)=>a-b);
    const chMatch = window.location.pathname.match(/\/read\/[^\/]+\/(\d+)/);
    const ch = chMatch ? chMatch[1] : '';
    const finalBook = bookName2 || 'الآية';
    if (uniq.length === 1) {
      refs.push(ch ? `${finalBook} ${ch}:${uniq[0]}` : `${finalBook} ${uniq[0]}`);
    } else if (uniq.length > 1) {
      // إذا كانت الآيات متتالية اعرضها كـ نطاق 21-22 بدل 21، 22
      const nums = uniq.map(n=>parseInt(n,10)).sort((a,b)=>a-b);
      const isConsecutive = nums.every((n,i)=> i===0 || n === nums[i-1]+1);
      if (isConsecutive) {
        refs.push(ch ? `${finalBook} ${ch}:${nums[0]}-${nums[nums.length-1]}` : `${nums[0]}-${nums[nums.length-1]}`);
      } else {
        refs.push(ch ? `${finalBook} ${ch}:${uniq.join('، ')}` : uniq.join('، '));
      }
    }
  }
  if (refs.length === 0) {
    const h1 = document.querySelector('h1');
    if(h1) refs.push(h1.innerText.split('—')[0].trim());
    firstOsis = document.querySelector('[data-osis]')?.getAttribute('data-osis') || '';
  }
  const pageUrl = firstOsis ? `${window.location.origin}/verse/${firstOsis}` : window.location.href;
  const attribution = ` ${refs.join('، ')}\nالمنصة الكتابية — ${pageUrl}`;
  const plain = selectedText + attribution;
  const html = `<div dir="rtl">${selectedText.replace(/\n/g,'<br>')} ${refs.join('، ')}<br><a href="${pageUrl}" style="color:#2563eb">المنصة الكتابية — ${pageUrl}</a></div>`;
  if (e.clipboardData) { e.clipboardData.setData('text/plain', plain); e.clipboardData.setData('text/html', html); e.preventDefault(); }
});
</script>
@endpush
@endsection
