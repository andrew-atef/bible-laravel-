<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ArabicConcordance;
use App\Models\Book;
use App\Models\Lexicon;
use App\Models\Verse;
use App\Services\Biblical\GeminiLexiconTranslationService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TranslateLexiconCommand extends Command
{
    /**
     * Automated Canonical Pipeline:
     * Gen.1.1 -> ... one word per request, 15 RPM / 500 RPD compliant.
     *
     * @var string
     */
    protected $signature = 'bible:translate-lexicon
                            {--canonical : Translate strictly in canonical reading order starting from Gen.1.1}
                            {--limit=500 : Maximum number of words to translate (default 500 RPD)}
                            {--delay=4.5 : Delay in seconds between requests to enforce 15 RPM}
                            {--verse= : Translate words in a verse or range (e.g. Gen.1.1 or Gen.1.1-Gen.1.5)}
                            {--strong= : Translate a specific Strong ID (e.g. H7225)}
                            {--force : Re-translate even if already translated}';

    protected $description = 'Translate English Strong lexicon entries to scholarly Arabic using Gemini API (15 RPM / 500 RPD compliant)';

    public function handle(GeminiLexiconTranslationService $translator): int
    {
        // إزالة قيود وقت التشغيل لتمكين الأمر من العمل لمدة ~40 دقيقة
        ini_set('max_execution_time', '0');
        set_time_limit(0);

        $strongOpt = $this->option('strong');
        $verseOpt = $this->option('verse');
        $force = (bool) $this->option('force');
        $canonical = (bool) $this->option('canonical');
        $limit = max(1, (int) $this->option('limit'));
        $delay = max(0.0, (float) $this->option('delay'));

        $this->info('🔍 Searching for untranslated words...');

        // 1. تحديد الكلمات: strong مفرد > نطاق آيات > الترتيب الكنسي من تكوين 1:1
        if ($strongOpt) {
            $strongIds = collect([strtoupper(trim((string) $strongOpt))])->filter();
        } elseif ($verseOpt) {
            $strongIds = $this->resolveStrongsFromVerseRange((string) $verseOpt);
        } else {
            // الافتراضي + --canonical: الترتيب الكنسي آية بآية بدءاً من Gen.1.1
            $strongIds = $this->resolveCanonicalUntranslated($limit, $force);
        }

        $strongIds = $strongIds->unique()->filter()->values();

        // احترام حد RPD عند تمرير verse/strong مع limit
        if ($strongIds->count() > $limit) {
            $strongIds = $strongIds->take($limit);
        }

        if ($strongIds->isEmpty()) {
            $this->info('✅ All words in scope are already translated!');
            return self::SUCCESS;
        }

        $total = $strongIds->count();
        $this->info("🚀 Starting translation of {$total} words (Single-word precision, 15 RPM rate-limited)...");
        $this->info('⏱️ Estimated time: '.round(($total * $delay) / 60, 1).' minutes.');
        if (! $canonical && ! $verseOpt && ! $strongOpt) {
            $this->comment('ℹ️ Canonical order (Gen.1.1 → Rev) is the default. Use --verse / --strong for targeted runs.');
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $successCount = 0;
        $failCount = 0;

        foreach ($strongIds as $index => $strongId) {
            $lexicon = Lexicon::where('strong_id', $strongId)->first();

            if ($lexicon === null) {
                $failCount++;
                Log::warning("Lexicon entry not found: {$strongId}");
                $bar->advance();
                continue;
            }

            try {
                // ترجمة كلمة واحدة بدقة متناهية
                $translated = $translator->translateLexicon($lexicon);

                $lexicon->update([
                    'short_gloss_ar' => $translated['short_gloss_ar'] ?? null,
                    'strongs_definition_ar' => $translated['strongs_definition_ar'] ?? null,
                    'kjv_translations_ar' => $translated['kjv_translations_ar'] ?? null,
                    'outline_json_ar' => $translated['outline_items_ar'] ?? $translated['outline_json_ar'] ?? [],
                    'translated_at' => now(),
                ]);

                $successCount++;
            } catch (Throwable $e) {
                $failCount++;
                $msg = $e->getMessage();
                Log::error("Translation failed for {$strongId}: {$msg}");

                // إذا وصلنا لحدود الكوتة اليومية 429 توقف فوراً
                if (str_contains($msg, '429') || str_contains($msg, 'QuotaExceeded') || str_contains($msg, 'RESOURCE_EXHAUSTED')) {
                    $this->newLine(2);
                    $this->error('🛑 Daily quota (RPD) or rate limit reached. Halting gracefully.');
                    $bar->advance();
                    break;
                }
            }

            $bar->advance();

            // فاصل زمني صارم لاحترام 15 RPM (4.5 ثوانٍ) ما لم تكن الكلمة الأخيرة
            if ($index < $total - 1 && $delay > 0) {
                usleep((int) ($delay * 1_000_000));
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('═════════════════════════════════════════════════');
        $this->info("🎉 Session Summary: {$successCount} translated successfully, {$failCount} failed.");
        $this->info('═════════════════════════════════════════════════');

        return self::SUCCESS;
    }

    /**
     * جلب أرقام سترونغ غير المترجمة بالترتيب الكنسي الدقيق بدءاً من تكوين 1:1.
     *
     * يستخدم GROUP BY + MIN(sort_key) لترتيب كل كلمة حسب أول ظهور لها
     * في القراءة القانونية: books.id (1=تكوين … 66=رؤيا) ثم chapter ثم verse ثم word_pos.
     * يعمل على السكيمة الحالية (books.id) ولا يعتمد على books.book_number.
     */
    private function resolveCanonicalUntranslated(int $limit, bool $force): Collection
    {
        // sort_key = book_id*1e9 + chapter*1e6 + verse*1e3 + word_pos (يفيض بأمان في 64-bit)
        $sortKey = '(books.id * 1000000000 + verses.chapter * 1000000 + verses.verse * 1000 + arabic_concordance.word_pos)';

        $query = DB::table('arabic_concordance')
            ->join('verses', 'verses.osis_id', '=', 'arabic_concordance.verse_osis')
            ->join('books', 'books.id', '=', 'verses.book_id')
            ->join('lexicons', 'lexicons.strong_id', '=', 'arabic_concordance.strong_id')
            ->select('arabic_concordance.strong_id', DB::raw("MIN({$sortKey}) as first_seen"))
            ->groupBy('arabic_concordance.strong_id')
            ->orderBy('first_seen', 'asc')
            ->limit($limit);

        if (! $force) {
            $query->whereNull('lexicons.translated_at');
        }

        return $query->pluck('strong_id');
    }

    /**
     * استخراج الكلمات من آية واحدة أو نطاق آيات (Gen.1.1 أو Gen.1.1-Gen.1.5).
     *
     * يستخدم مقارنة قانونية (book_id, chapter, verse) بدل المقارنة النصية
     * لـ osis_id التي تفشل مع Gen.10 مقابل Gen.2.
     */
    private function resolveStrongsFromVerseRange(string $range): Collection
    {
        $range = trim($range);

        if (! str_contains($range, '-')) {
            return ArabicConcordance::where('verse_osis', $range)->distinct()->pluck('strong_id');
        }

        [$startRaw, $endRaw] = array_map('trim', explode('-', $range, 2));

        $start = $this->parseOsis($startRaw);
        $end = $this->parseOsis($endRaw, $start);

        if ($start === null || $end === null) {
            // تراجع آمن: مطابقة نصية إذا تعذر تحليل OSIS
            $verses = Verse::where('osis_id', '>=', $startRaw)->where('osis_id', '<=', $endRaw)->pluck('osis_id');

            return ArabicConcordance::whereIn('verse_osis', $verses)->distinct()->pluck('strong_id');
        }

        $startBookId = $this->bookIdForCode($start['book']);
        $endBookId = $this->bookIdForCode($end['book']);

        if ($startBookId === null || $endBookId === null) {
            return collect();
        }

        // نطاق داخل نفس السفر: قيد chapter/verse فقط.
        if ($startBookId === $endBookId) {
            $verseOsisList = Verse::where('book_id', $startBookId)
                ->where(function ($q) use ($start, $end): void {
                    $q->where('chapter', '>', $start['chapter'])
                        ->orWhere(function ($q) use ($start): void {
                            $q->where('chapter', $start['chapter'])->where('verse', '>=', $start['verse']);
                        });
                })
                ->where(function ($q) use ($end): void {
                    $q->where('chapter', '<', $end['chapter'])
                        ->orWhere(function ($q) use ($end): void {
                            $q->where('chapter', $end['chapter'])->where('verse', '<=', $end['verse']);
                        });
                })
                ->pluck('osis_id');

            return ArabicConcordance::whereIn('verse_osis', $verseOsisList)->distinct()->pluck('strong_id');
        }

        // نطاق عبر أسفار: (ذيل سفر البداية) OR (أسفار وسطى كاملة) OR (رأس سفر النهاية).
        $verseOsisList = Verse::query()
            ->whereBetween('book_id', [$startBookId, $endBookId])
            ->where(function ($q) use ($start, $end, $startBookId, $endBookId): void {
                $q->where(function ($q) use ($start, $startBookId): void {
                    // سفر البداية: من (chapter,verse) البداية فصاعداً
                    $q->where('book_id', $startBookId)
                        ->where(function ($q) use ($start): void {
                            $q->where('chapter', '>', $start['chapter'])
                                ->orWhere(function ($q) use ($start): void {
                                    $q->where('chapter', $start['chapter'])
                                        ->where('verse', '>=', $start['verse']);
                                });
                        });
                })
                ->orWhere(function ($q) use ($startBookId, $endBookId): void {
                    // الأسفار الوسطى كاملة
                    if ($endBookId - $startBookId > 1) {
                        $q->where('book_id', '>', $startBookId)->where('book_id', '<', $endBookId);
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->orWhere(function ($q) use ($end, $endBookId): void {
                    // سفر النهاية: حتى (chapter,verse) النهاية
                    $q->where('book_id', $endBookId)
                        ->where(function ($q) use ($end): void {
                            $q->where('chapter', '<', $end['chapter'])
                                ->orWhere(function ($q) use ($end): void {
                                    $q->where('chapter', $end['chapter'])
                                        ->where('verse', '<=', $end['verse']);
                                });
                        });
                });
            })
            ->pluck('osis_id');

        return ArabicConcordance::whereIn('verse_osis', $verseOsisList)->distinct()->pluck('strong_id');
    }

    /**
     * تحليل مرجع OSIS بصيغ: Book.C.V أو C.V (يرث السفر) أو V (يرث السفر والإصحاح).
     *
     * @return array{book:string, chapter:int, verse:int}|null
     */
    private function parseOsis(string $ref, ?array $fallback = null): ?array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        $parts = explode('.', $ref);

        if (count($parts) === 3) {
            [$book, $ch, $vs] = $parts;
            if (! is_numeric($ch) || ! is_numeric($vs)) {
                return null;
            }

            return ['book' => trim($book), 'chapter' => (int) $ch, 'verse' => (int) $vs];
        }

        if (count($parts) === 2 && $fallback !== null) {
            [$ch, $vs] = $parts;
            if (! is_numeric($ch) || ! is_numeric($vs)) {
                return null;
            }

            return ['book' => $fallback['book'], 'chapter' => (int) $ch, 'verse' => (int) $vs];
        }

        if (count($parts) === 1 && is_numeric($parts[0]) && $fallback !== null) {
            return ['book' => $fallback['book'], 'chapter' => $fallback['chapter'], 'verse' => (int) $parts[0]];
        }

        return null;
    }

    private function bookIdForCode(string $code): ?int
    {
        $book = Book::where('code', trim($code))->first(['id']);

        return $book?->id;
    }
}
