<?php

declare(strict_types=1);

namespace App\Services\Biblical;

use App\Models\ArabicConcordance;
use App\Models\Lexicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * LexiconStudyService — lexical depth for Strong's entries
 *
 * Provides:
 *  - Summary with Top-5 Arabic SVD renderings + morphology breakdown
 *  - Paginated concordance occurrences with Arabic context + highlighted tokens
 */
class LexiconStudyService
{
    public function __construct(
        private readonly ArabicScriptService $arabicScript
    ) {}

    /**
     * Get lexicon summary.
     *
     * @return array{
     *   lexicon: array,
     *   occurrences_count: int,
     *   top_arabic_translations: array<int, array{arabic_word:string, arabic_clean:string, count:int}>,
     *   grammatical_forms: array<int, array{morphology:string, count:int}>,
     *   related_strongs: array
     * }|null  Null if Strong ID unknown
     */
    public function getLexiconSummary(string $strongId): ?array
    {
        $strongId = strtoupper(trim($strongId));

        if ($strongId === '' || ! preg_match('/^[HG]\d+$/', $strongId)) {
            return null;
        }

        $cacheKey = 'lexicon_summary_' . $strongId;
        $storeSupportsTags = method_exists(Cache::getStore(), 'tags');

        $callback = function () use ($strongId): ?array {
            // جلب مدخل الكلمة مع بيانات الجذر الأصلية كاملة
            $lexicon = Lexicon::query()
                ->select([
                    'strong_id', 'language', 'lemma', 'lemma_clean', 'transliteration',
                    'pronunciation', 'part_of_speech', 'word_origin', 'root_strong',
                    'strongs_definition', 'strongs_definition_ar',
                    'kjv_translations', 'kjv_translations_ar',
                    'outline_json', 'outline_json_ar',
                    'short_gloss_ar', 'translated_at',
                ])
                ->with([
                    // جلب النطق والتعريف للجذر أيضاً
                    'rootLexicon:strong_id,lemma,transliteration,pronunciation,strongs_definition,kjv_translations,language'
                ])
                ->where('strong_id', $strongId)
                ->first();

            if ($lexicon === null) {
                return null;
            }

            // تجهيز بيانات الجذر التفصيلية (نطق + معنى عربي + معنى إنجليزي)
            $rootInfo = null;
            if ($lexicon->rootLexicon) {
                $root = $lexicon->rootLexicon;

                // 1. استخراج معنى إنجليزي مقتضب جداً من بداية التعريف (مثل: "I" أو "head")
                $cleanEnDef = '';
                if (!empty($root->strongs_definition)) {
                    // أخذ أول كلمة أو عبارة قبل الأقواس والفواصل
                    if (preg_match('/^([a-zA-Z\s]+?)(?:[\(;,\.]|$)/', trim($root->strongs_definition), $m)) {
                        $cleanEnDef = trim($m[1]);
                    }
                }

                // 2. استخراج أشهر ترجمة عربية ورد بها هذا الجذر في فان دايك (مثل: "أَنَا" أو "رَأْس")
                $rootArabic = DB::table('arabic_concordance')
                    ->where('strong_id', $root->strong_id)
                    ->groupBy('arabic_word')
                    ->orderByDesc(DB::raw('COUNT(*)'))
                    ->value('arabic_word');

                // 3. تنظيف النطق الصوتي للجذر
                $rootPron = trim(str_replace(["'", 'ʼ', 'ʻ', '`'], '', $root->pronunciation ?: $root->transliteration ?: ''));

                $rootInfo = [
                    'strong_id'     => $root->strong_id,
                    'lemma'         => $root->lemma,
                    'pronunciation' => $rootPron,
                    'meaning_en'    => $cleanEnDef ?: null,
                    'meaning_ar'    => $rootArabic ?: null,
                ];
            }

            // Occurrences count (fast indexed lookup on strong_id)
            $occurrencesCount = (int) DB::table('arabic_concordance')
                ->where('strong_id', $strongId)
                ->count();

            // Top 5 Arabic translations in SVD — aggregated from arabic_concordance
            // Use arabic_word (vocalized) grouped, with clean as secondary for display
            // Select only required, indexed path
            $topArabic = DB::table('arabic_concordance')
                ->select('arabic_word', 'arabic_clean', DB::raw('COUNT(*) as cnt'))
                ->where('strong_id', $strongId)
                ->groupBy('arabic_word', 'arabic_clean')
                ->orderByDesc('cnt')
                ->limit(5)
                ->get()
                ->map(fn ($row): array => [
                    'arabic_word' => $row->arabic_word,
                    'arabic_clean' => $row->arabic_clean,
                    // Also provide normalized for search UI
                    'arabic_normalized' => $this->arabicScript->normalizeForSearch($row->arabic_clean),
                    'count' => (int) $row->cnt,
                ])
                ->toArray();

            // بدلاً من استعلام morphology المشفر القديم، استخرج الأشكال العبرية/اليونانية الحقيقية وتكراراتها:
            $hebrewForms = DB::table('arabic_concordance')
                ->select('source_word', DB::raw('COUNT(*) as cnt'))
                ->where('strong_id', $strongId)
                ->whereNotNull('source_word')
                ->groupBy('source_word')
                ->orderByDesc('cnt')
                ->limit(8)
                ->get()
                ->map(fn ($row): array => [
                    'word' => $row->source_word,
                    'count' => (int) $row->cnt,
                ])
                ->toArray();

            // Keep old grammatical_forms for backward compat (empty to hide طلاسم)
            $gramForms = [];

            // استخراج نطق بسيط وصافٍ مثل 'el' أو 'reshith'
            $simpleTranslit = strtolower(trim(str_replace(["'", 'ʼ', 'ʻ', '`', 'ê', 'î', 'ô', 'û'], ['','','','','e','i','o','u'], $lexicon->transliteration ?? '')));
            if (!$simpleTranslit) {
                $simpleTranslit = strtolower(trim(str_replace(["'", '-'], '', $lexicon->pronunciation ?? '')));
            }

            // Outline items (already cast to array on model)
            $outline = $lexicon->outline_json;
            if (is_string($outline)) {
                $decoded = json_decode($outline, true);
                $outline = is_array($decoded) ? $decoded : [];
            }
            if ($outline === null) {
                $outline = [];
            }

            // Fallback for outline_ar
            $outlineAr = $lexicon->outline_json_ar;
            if (is_string($outlineAr)) {
                $decodedAr = json_decode($outlineAr, true);
                $outlineAr = is_array($decodedAr) ? $decodedAr : [];
            }
            if ($outlineAr === null) {
                $outlineAr = [];
            }

            return [
                'lexicon' => [
                    'strong_id' => $lexicon->strong_id,
                    'language' => $lexicon->language,
                    'lemma' => $lexicon->lemma,
                    'lemma_clean' => $lexicon->lemma_clean,
                    'transliteration' => $lexicon->transliteration,
                    'pronunciation' => $lexicon->pronunciation,
                    'clean_translit' => $simpleTranslit ?: strtolower($lexicon->strong_id),
                    'part_of_speech' => $lexicon->part_of_speech,
                    'word_origin' => $lexicon->word_origin,
                    'root_strong' => $lexicon->root_strong,
                    'root_info' => $rootInfo, // الحقل الجديد الغني بالمعلومات
                    'strongs_definition' => $lexicon->strongs_definition,
                    'strongs_definition_ar' => $lexicon->strongs_definition_ar,
                    'kjv_translations' => $lexicon->kjv_translations,
                    'kjv_translations_ar' => $lexicon->kjv_translations_ar,
                    'outline_items' => $outline,
                    'outline_json_ar' => $outlineAr,
                    'short_gloss_ar' => $lexicon->short_gloss_ar,
                    'translated_at' => $lexicon->translated_at,
                ],
                'occurrences_count' => $occurrencesCount,
                'top_arabic_translations' => $topArabic,
                'grammatical_forms' => $gramForms,
                'hebrew_forms' => $hebrewForms,
                'related_strongs' => [
                    'root' => $lexicon->root_strong,
                    'derived_count' => $lexicon->derivedLexicons()->count(),
                ],
            ];
        };

        if ($storeSupportsTags) {
            $result = Cache::tags(['lexicon', 'lexicon_summary'])->remember($cacheKey, 604800, $callback);
        } else {
            $result = Cache::remember($cacheKey, 604800, $callback);
        }

        /** @var array|null $result */
        return $result;
    }

    /**
     * Paginated concordance occurrences for a Strong ID.
     *
     * Each item includes highlighted Arabic context + source token.
     *
     * @return LengthAwarePaginator<int, array{
     *   id:int,
     *   verse_osis:string,
     *   book: ?array,
     *   verse: ?array,
     *   word_pos:int,
     *   source_word:string,
     *   transliteration:string,
     *   arabic_word:string,
     *   gloss_en:?string,
     *   morphology:?string,
     *   arabic_context:string,
     *   arabic_context_highlighted:string,
     *   source_context_highlighted:string
     * }>
     */
    public function getConcordanceOccurrences(string $strongId, int $page = 1, int $perPage = 30): LengthAwarePaginator
    {
        $strongId = strtoupper(trim($strongId));
        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);

        // Guard: unknown Strong → empty paginator
        $exists = Lexicon::where('strong_id', $strongId)->exists();
        if (! preg_match('/^[HG]\d+$/', $strongId) || ! $exists) {
            return new ConcretePaginator([], 0, $perPage, $page, ['path' => request()?->url() ?? '/']);
        }

        // الترتيب الكنسي السليم: يبدأ من التكوين وينتهي بالرؤيا
        $query = ArabicConcordance::query()
            ->select([
                'arabic_concordance.id', 'arabic_concordance.verse_osis', 'arabic_concordance.word_pos', 
                'arabic_concordance.strong_id', 'arabic_concordance.source_word', 'arabic_concordance.source_clean',
                'arabic_concordance.transliteration', 'arabic_concordance.arabic_word', 'arabic_concordance.arabic_clean', 
                'arabic_concordance.gloss_en', 'arabic_concordance.morphology', 'arabic_concordance.all_strongs',
            ])
            ->join('verses', 'verses.osis_id', '=', 'arabic_concordance.verse_osis')
            ->join('books', 'books.id', '=', 'verses.book_id')
            ->where('arabic_concordance.strong_id', $strongId)
            ->orderBy('books.book_number') // 1 (تكوين), 2 (خروج) ... 66 (رؤيا)
            ->orderBy('verses.chapter')
            ->orderBy('verses.verse')
            ->orderBy('arabic_concordance.word_pos');

        // Paginate (profiled: select count is cheap on indexed strong_id)
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Batch-hydrate verse contexts for N+1 guard:
        // Collect unique verse_osis on this page, fetch all tokens for those verses in one query
        $osisOnPage = $paginator->getCollection()->pluck('verse_osis')->unique()->values()->toArray();

        $contextMap = []; // verse_osis => ['arabic_sentence'=>..., 'source_sentence'=>..., 'verse'=>..., 'book'=>...]
        if ($osisOnPage !== []) {
            // All tokens for verses on page, ordered
            $allTokens = ArabicConcordance::query()
                ->select(['verse_osis', 'word_pos', 'arabic_word', 'source_word'])
                ->whereIn('verse_osis', $osisOnPage)
                ->orderBy('verse_osis')
                ->orderBy('word_pos')
                ->get()
                ->groupBy('verse_osis');

            // Fetch verse + book meta in one query
            $versesMeta = DB::table('verses')
                ->join('books', 'books.id', '=', 'verses.book_id')
                ->whereIn('verses.osis_id', $osisOnPage)
                ->select(['verses.osis_id', 'verses.chapter', 'verses.verse', 'books.code', 'books.name_ar', 'books.name_en', 'books.slug'])
                ->get()
                ->keyBy('osis_id');

            foreach ($osisOnPage as $osis) {
                $tokens = $allTokens->get($osis, new Collection());
                $arabicSentence = $tokens->pluck('arabic_word')->implode(' ');
                $sourceSentence = $tokens->pluck('source_word')->implode(' ');
                $meta = $versesMeta->get($osis);
                $contextMap[$osis] = [
                    'arabic_sentence' => $arabicSentence,
                    'source_sentence' => $sourceSentence,
                    'meta' => $meta,
                ];
            }
        }

        // Transform paginator items
        $paginator->getCollection()->transform(function (ArabicConcordance $occ) use ($contextMap): array {
            $osis = $occ->verse_osis;
            $ctx = $contextMap[$osis] ?? null;
            $arabicSentence = $ctx['arabic_sentence'] ?? $occ->arabic_word;
            $sourceSentence = $ctx['source_sentence'] ?? $occ->source_word;
            $meta = $ctx['meta'] ?? null;

            // Highlight matched tokens (preserves original diacritics in output)
            $arabicHighlighted = $this->arabicScript->highlightToken($arabicSentence, $occ->arabic_word);
            $sourceHighlighted = $this->arabicScript->highlightToken($sourceSentence, $occ->source_word);

            return [
                'id' => $occ->id,
                'verse_osis' => $occ->verse_osis,
                'book' => $meta ? [
                    'code' => $meta->code,
                    'name_ar' => $meta->name_ar,
                    'name_en' => $meta->name_en,
                    'slug' => $meta->slug,
                ] : null,
                'verse' => $meta ? [
                    'chapter' => (int) $meta->chapter,
                    'verse' => (int) $meta->verse,
                    'osis_id' => $meta->osis_id,
                ] : null,
                'word_pos' => $occ->word_pos,
                'strong_id' => $occ->strong_id,
                'source_word' => $occ->source_word,
                'transliteration' => $occ->transliteration,
                'arabic_word' => $occ->arabic_word,
                'arabic_clean' => $occ->arabic_clean,
                'gloss_en' => $occ->gloss_en,
                'morphology' => $occ->morphology,
                'all_strongs' => $occ->all_strongs,
                'arabic_context' => $arabicSentence,
                'arabic_context_highlighted' => $arabicHighlighted,
                'source_context_highlighted' => $sourceHighlighted,
            ];
        });

        return $paginator;
    }
}
