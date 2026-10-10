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

class LexiconStudyService
{
    public function __construct(
        private readonly ArabicScriptService $arabicScript
    ) {}

public function getLexiconSummary(string $strongId): ?array
    {
        $strongId = strtoupper(trim($strongId));
        if ($strongId === '' || ! preg_match('/^[HG]\d+$/', $strongId)) {
            return null;
        }
        $cacheKey = 'lexicon_summary_v3_' . $strongId;
        $storeSupportsTags = method_exists(Cache::getStore(), 'tags');
        $callback = function () use ($strongId): ?array {
            $lexicon = Lexicon::query()
                ->select([
                    'strong_id', 'language', 'lemma', 'lemma_clean', 'transliteration',
                    'pronunciation', 'part_of_speech', 'word_origin', 'root_strongs', 'roots_json',
                    'strongs_definition', 'strongs_definition_ar', // 🌟 نطلب الشرح العربي
                    'kjv_translations', 'kjv_translations_ar',     // 🌟 نطلب الترجمات الشائعة عربي
                    'outline_json', 'outline_json_ar',             // 🌟 نطلب النقاط الشجرية عربي
                    'short_gloss_ar', 'translated_at',             // 🌟 نطلب المعنى المختصر العربي
                    'is_indexed',
                ])
                ->where('strong_id', $strongId)
                ->first();

            if ($lexicon === null) {
                return null;
            }

            $rootInfo = null;
            $rootInfos = [];
            $allRootIds = $lexicon->resolveRootIds();

            if (!empty($allRootIds)) {
                $roots = Lexicon::whereIn('strong_id', $allRootIds)->get()->keyBy('strong_id');
                foreach ($allRootIds as $rid) {
                    $root = $roots->get($rid);
                    if (!$root) continue;
                    $cleanEnDef = '';
                    if (!empty($root->strongs_definition)) {
                        if (preg_match('/^([a-zA-Z\s]+?)(?:[\(;,\.]|$)/', trim($root->strongs_definition), $m)) {
                            $cleanEnDef = trim($m[1]);
                        }
                    }
                    $rootArabic = DB::table('arabic_concordance')
                        ->where('strong_id', $root->strong_id)
                        ->groupBy('arabic_word')
                        ->orderByDesc(DB::raw('COUNT(*)'))
                        ->value('arabic_word');

                    if (empty($rootArabic)) {
                        $rootArabic = $root->short_gloss_ar ?? null;
                    }

                    $rootPron = trim(str_replace(["'", 'ʼ', 'ʻ', '`'], '', $root->pronunciation ?: $root->transliteration ?: ''));
                    $info = [
                        'strong_id'     => $root->strong_id,
                        'lemma'         => $root->lemma,
                        'pronunciation' => $rootPron,
                        'meaning_en'    => $cleanEnDef ?: null,
                        'meaning_ar'    => $rootArabic ?: null,
                    ];
                    $rootInfos[] = $info;
                    if ($rootInfo === null) {
                        $rootInfo = $info;
                    }
                }
                if ($rootInfo === null && !empty($rootInfos)) {
                    $rootInfo = $rootInfos[0];
                }
            }

            $occurrencesCount = (int) DB::table('arabic_concordance')->where('strong_id', $strongId)->count();

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
                    'arabic_normalized' => $this->arabicScript->normalizeForSearch($row->arabic_clean),
                    'count' => (int) $row->cnt,
                ])->toArray();

            $hebrewForms = DB::table('arabic_concordance')
                ->select('source_word', DB::raw('COUNT(*) as cnt'))
                ->where('strong_id', $strongId)
                ->whereNotNull('source_word')
                ->groupBy('source_word')
                ->orderByDesc('cnt')
                ->limit(8)
                ->get()
                ->map(fn ($row): array => ['word' => $row->source_word, 'count' => (int) $row->cnt])->toArray();

            $gramForms = [];
            $simpleTranslit = strtolower(trim(str_replace(["'", 'ʼ', 'ʻ', '`', 'ê', 'î', 'ô', 'û'], ['','','','','e','i','o','u'], $lexicon->transliteration ?? '')));
            if (!$simpleTranslit) {
                $simpleTranslit = strtolower(trim(str_replace(["'", '-'], '', $lexicon->pronunciation ?? '')));
            }

            // Outline English
            $outline = $lexicon->outline_json;
            if (is_string($outline)) {
                $decoded = json_decode($outline, true);
                $outline = is_array($decoded) ? $decoded : [];
            }
            if ($outline === null) $outline = [];

            // Outline Arabic
            $outlineAr = $lexicon->outline_json_ar;
            if (is_string($outlineAr)) {
                $decodedAr = json_decode($outlineAr, true);
                $outlineAr = is_array($decodedAr) ? $decodedAr : [];
            }
            if ($outlineAr === null) $outlineAr = [];

            // 🌟 القراءة الحقيقية للترجمة العربية المعتمدة من الداتابيز:
            $defAr = $lexicon->strongs_definition_ar
                ?: ($lexicon->strongs_definition ? trim(explode(';', $lexicon->strongs_definition)[0]) : null);

            $shortGlossAr = $lexicon->short_gloss_ar
                ?: $defAr;

            $kjvEnItems = $this->splitKjvTranslations($lexicon->kjv_translations ?? '');
            $kjvArItems = $this->splitKjvTranslations($lexicon->kjv_translations_ar ?? '');

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
                    'root_strongs' => $lexicon->root_strongs,
                    'roots_json' => $lexicon->roots_json,
                    'root_ids' => $allRootIds,
                    'root_info' => $rootInfo,
                    'root_infos' => $rootInfos,
                    'strongs_definition' => $lexicon->strongs_definition,
                    'strongs_definition_ar' => $defAr,             // ✅ الشرح العربي المترجم
                    'kjv_translations' => $lexicon->kjv_translations,
                    'kjv_translations_ar' => $lexicon->kjv_translations_ar,
                    'outline_items' => $outline,
                    'outline_json_ar' => $outlineAr,
                    'short_gloss_ar' => $shortGlossAr,             // ✅ المعنى الصافي العربي
                    'kjv_items_en' => $kjvEnItems,
                    'kjv_items_ar' => $kjvArItems,
                    'translated_at' => $lexicon->translated_at,
                ],
                'occurrences_count' => $occurrencesCount,
                'top_arabic_translations' => $topArabic,
                'grammatical_forms' => $gramForms,
                'hebrew_forms' => $hebrewForms,
                'related_strongs' => [
                    'root' => $allRootIds[0] ?? null,
                    'roots' => $allRootIds,
                    'derived_count' => $lexicon->derivedLexiconsMulti()->count(),
                ],
            ];
        };

        if ($storeSupportsTags) {
            $result = Cache::tags(['lexicon', 'lexicon_summary'])->remember($cacheKey, 604800, $callback);
        } else {
            $result = Cache::remember($cacheKey, 604800, $callback);
        }

        return $result;
    }

    /**
     * Clean string splitting for KJV translations — respects parentheses depth.
     * Moved from Blade $splitKjv closure per TASK 4 (Golden Rule).
     * @return string[]
     */
    public function splitKjvTranslations(string $raw): array
    {
        $s = trim(str_replace([':--', '--'], '', $raw));
        $s = trim($s, " \t\n\r\0\x0B.,;:-");
        if ($s === '') return [];
        $res = []; $cur = ''; $depth = 0;
        $len = mb_strlen($s, 'UTF-8');
        for ($i=0; $i<$len; $i++) {
            $ch = mb_substr($s, $i, 1, 'UTF-8');
            if ($ch==='(') $depth++;
            if ($ch===')') $depth = max(0,$depth-1);
            if (($ch===',' || $ch==='،' || $ch===';' || $ch==='؛') && $depth===0) {
                $res[] = trim($cur); $cur = '';
            } else {
                $cur .= $ch;
            }
        }
        if (trim($cur) !== '') $res[] = trim($cur);
        return array_values(array_filter(array_map(fn($x)=>trim($x," \t\n\r\0\x0B.;:-"), $res)));
    }

    public function getConcordanceOccurrences(string $strongId, int $page = 1, int $perPage = 30): LengthAwarePaginator
    {
        $strongId = strtoupper(trim($strongId));
        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);
        $exists = Lexicon::where('strong_id', $strongId)->exists();
        if (! preg_match('/^[HG]\d+$/', $strongId) || ! $exists) {
            return new ConcretePaginator([], 0, $perPage, $page, ['path' => request()?->url() ?? '/']);
        }
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
            ->orderBy('books.id')
            ->orderBy('verses.chapter')
            ->orderBy('verses.verse')
            ->orderBy('arabic_concordance.word_pos');
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $osisOnPage = $paginator->getCollection()->pluck('verse_osis')->unique()->values()->toArray();
        $contextMap = [];
        if ($osisOnPage !== []) {
            $allTokens = ArabicConcordance::query()
                ->select(['verse_osis', 'word_pos', 'arabic_word', 'source_word'])
                ->whereIn('verse_osis', $osisOnPage)
                ->orderBy('verse_osis')->orderBy('word_pos')->get()->groupBy('verse_osis');
            $versesMeta = DB::table('verses')
                ->join('books', 'books.id', '=', 'verses.book_id')
                ->whereIn('verses.osis_id', $osisOnPage)
                ->select(['verses.osis_id', 'verses.chapter', 'verses.verse', 'books.code', 'books.name_ar', 'books.name_en', 'books.slug'])
                ->get()->keyBy('osis_id');
            foreach ($osisOnPage as $osis) {
                $tokens = $allTokens->get($osis, new Collection());
                $arabicSentence = $tokens->pluck('arabic_word')->implode(' ');
                $sourceSentence = $tokens->pluck('source_word')->implode(' ');
                $meta = $versesMeta->get($osis);
                $contextMap[$osis] = ['arabic_sentence' => $arabicSentence, 'source_sentence' => $sourceSentence, 'meta' => $meta];
            }
        }
        $paginator->getCollection()->transform(function (ArabicConcordance $occ) use ($contextMap): array {
            $osis = $occ->verse_osis;
            $ctx = $contextMap[$osis] ?? null;
            $arabicSentence = $ctx['arabic_sentence'] ?? $occ->arabic_word;
            $sourceSentence = $ctx['source_sentence'] ?? $occ->source_word;
            $meta = $ctx['meta'] ?? null;
            $arabicHighlighted = $this->arabicScript->highlightToken($arabicSentence, $occ->arabic_word);
            $sourceHighlighted = $this->arabicScript->highlightToken($sourceSentence, $occ->source_word);
            return [
                'id' => $occ->id,
                'verse_osis' => $occ->verse_osis,
                'book' => $meta ? ['code' => $meta->code, 'name_ar' => $meta->name_ar, 'name_en' => $meta->name_en, 'slug' => $meta->slug] : null,
                'verse' => $meta ? ['chapter' => (int) $meta->chapter, 'verse' => (int) $meta->verse, 'osis_id' => $meta->osis_id] : null,
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
