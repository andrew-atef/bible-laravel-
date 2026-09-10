<?php

declare(strict_types=1);

namespace App\Services\Biblical;

use App\Models\ArabicConcordance;
use App\Models\Book;
use App\Models\Commentary;
use App\Models\Verse;
use App\Services\Biblical\CommentaryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * BibleReaderService — high-speed chapter / verse retrieval for SEO rendering
 *
 * Guarantees:
 *  - No N+1 (eager-load tokens + lexicon where needed)
 *  - Select only required columns
 *  - HTML-safe Arabic handling via ArabicScriptService
 *  - Cache-aware (86400s) with tag fallback for high-traffic
 */
class BibleReaderService
{
    public function __construct(
        private readonly ArabicScriptService $arabicScript,
        private readonly CommentaryService $commentaryService = new CommentaryService()
    ) {}

    /**
     * Get full chapter payload.
     *
     * @return array{
     *   book: array,
     *   chapter: int,
     *   verses: Collection,
     *   verses_count: int,
     *   commentaries: Collection,
     *   next_chapter: ?array,
     *   prev_chapter: ?array
     * }
     */
    public function getChapter(string $bookSlug, int $chapter): array
    {
        $cacheKey = $this->chapterCacheKey($bookSlug, $chapter);

        // Use tags if store supports them (redis, database, array with Taggable), else plain remember
        $storeSupportsTags = method_exists(Cache::getStore(), 'tags');

        $callback = function () use ($bookSlug, $chapter): array {
            /** @var Book|null $book */
            $book = Book::findByIdentifier($bookSlug);
            if ($book) {
                // Re-select only needed columns for cache payload (keep model lightweight) + is_indexed
                $book = Book::query()
                    ->select(['id', 'code', 'testament', 'book_number', 'name_ar', 'name_en', 'slug', 'is_indexed'])
                    ->where('id', $book->id)
                    ->first();
            }

            if ($book === null) {
                return $this->emptyChapter($bookSlug, $chapter);
            }

            // Eager-load verses + tokens (ordered) + token lexicon (minimal columns)
            // Profiled: select only needed, ordered by word_pos via relationship
            $verses = Verse::query()
                ->select(['id', 'book_id', 'chapter', 'verse', 'osis_id', 'is_indexed'])
                ->where('book_id', $book->id)
                ->where('chapter', $chapter)
                ->orderBy('verse')
                ->with([
                    'tokens' => function ($q): void {
                        $q->select(['id', 'verse_osis', 'word_pos', 'strong_id', 'source_word', 'source_clean', 'transliteration', 'arabic_word', 'arabic_clean', 'gloss_en', 'morphology', 'all_strongs'])
                            ->orderBy('word_pos');
                    },
                    // Shallow lexicon for quick gloss popup (avoid N+1) — include pronunciation + short_gloss_ar for drawer
                    'tokens.lexicon:strong_id,lemma,lemma_clean,transliteration,pronunciation,language,part_of_speech,short_gloss_ar',
                ])
                ->get();

            // Commentaries matching chapter: verse_start LIKE "Gen.1.%"  OR passage covering chapter
            // Build OSIS prefix for LIKE: e.g. Gen.1.%
            $osisPrefix = $book->code . '.' . $chapter . '.';
            $commentaries = Commentary::query()
                ->select(['id', 'author_name', 'era', 'verse_start', 'verse_end', 'target_phrase', 'strong_id', 'level', 'title', 'body_html', 'source_book'])
                ->where(function ($q) use ($osisPrefix, $book, $chapter): void {
                    $q->where('verse_start', 'like', $osisPrefix . '%')
                        // verse-level exact chapter prefix already covers verse-level
                        ->orWhere(function ($q) use ($osisPrefix, $book, $chapter): void {
                            // passage-level that spans this chapter: e.g. Gen.1.1 – Gen.2.3
                            $chapterStart = $book->code . '.' . $chapter . '.1';
                            $q->where('level', 'passage')
                                ->where('verse_start', '<=', $chapterStart)
                                ->where(function ($q) use ($osisPrefix): void {
                                    $q->whereNull('verse_end')
                                        ->orWhere('verse_end', '>=', $osisPrefix . '1')
                                        ->orWhere('verse_end', 'like', $osisPrefix . '%');
                                });
                        });
                })
                ->orderByRaw("CASE level WHEN 'passage' THEN 1 WHEN 'verse' THEN 2 WHEN 'word' THEN 3 ELSE 4 END")
                ->limit(20)
                ->get();

            // Next / prev chapter navigation (book-aware)
            $nextChapter = $this->resolveAdjacentChapter($book, $chapter, 1);
            $prevChapter = $this->resolveAdjacentChapter($book, $chapter, -1);

            return [
                'book' => $book->toArray(),
                'chapter' => $chapter,
                'verses' => $verses,
                'verses_count' => $verses->count(),
                'commentaries' => $commentaries,
                'next_chapter' => $nextChapter,
                'prev_chapter' => $prevChapter,
            ];
        };

        if ($storeSupportsTags) {
            return Cache::tags(['bible', 'chapters'])->remember($cacheKey, 604800, $callback);
        }

        return Cache::remember($cacheKey, 604800, $callback);
    }

    /**
     * Get verse study payload (interlinear + commentaries + cross-refs).
     *
     * @return array{
     *   verse: ?array,
     *   book: ?array,
     *   tokens: Collection,
     *   interlinear: array,
     *   commentaries: Collection,
     *   cross_references: Collection,
     *   next_verse: ?array,
     *   prev_verse: ?array
     * }|null
     */
    public function getVerseStudy(string $osisId): array
    {
        $cacheKey = 'verse_study_' . str_replace('.', '_', $osisId);

        $storeSupportsTags = method_exists(Cache::getStore(), 'tags');

        $callback = function () use ($osisId): array {
            $verse = Verse::query()
                ->select(['id', 'book_id', 'chapter', 'verse', 'osis_id', 'is_indexed'])
                ->where('osis_id', $osisId)
                ->with([
                    'book:id,code,testament,book_number,name_ar,name_en,slug,is_indexed',
                    'tokens' => function ($q): void {
                        $q->select(['id', 'verse_osis', 'word_pos', 'strong_id', 'source_word', 'source_clean', 'transliteration', 'arabic_word', 'arabic_clean', 'gloss_en', 'morphology', 'all_strongs'])
                            ->orderBy('word_pos');
                    },
                    'tokens.lexicon:strong_id,lemma,lemma_clean,transliteration,pronunciation,language,part_of_speech,short_gloss_ar,strongs_definition',
                ])
                ->first();

            if ($verse === null) {
                // Fallback: token exists but verse spine missing? Try direct concordance
                // Return minimal payload with tokens only
                $tokens = ArabicConcordance::query()
                    ->select(['id', 'verse_osis', 'word_pos', 'strong_id', 'source_word', 'transliteration', 'arabic_word', 'gloss_en', 'morphology'])
                    ->where('verse_osis', $osisId)
                    ->orderBy('word_pos')
                    ->with('lexicon:strong_id,lemma,transliteration,language')
                    ->get();

                if ($tokens->isEmpty()) {
                    return [
                        'verse' => null,
                        'book' => null,
                        'tokens' => new Collection(),
                        'interlinear' => [],
                        'commentaries' => new Collection(),
                        'cross_references' => new Collection(),
                        'next_verse' => null,
                        'prev_verse' => null,
                    ];
                }

                // Synthesize verse array from OSIS
                [$code, $ch, $v] = array_pad(explode('.', $osisId), 3, null);
                $book = Book::where('code', $code)->select(['id', 'code', 'name_ar', 'name_en', 'slug'])->first();

                $interlinear = $this->buildInterlinear($tokens);
                // Use CommentaryService for canonical range + word/phrase handling
                $commentaries = $this->commentaryService->forVerse($osisId)->take(10);
                $crossRefs = $this->findCrossReferences($tokens, $osisId, 5);

                return [
                    'verse' => ['osis_id' => $osisId, 'chapter' => (int) $ch, 'verse' => (int) $v],
                    'book' => $book?->toArray(),
                    'tokens' => $tokens,
                    'interlinear' => $interlinear,
                    'commentaries' => $commentaries,
                    'cross_references' => $crossRefs,
                    'next_verse' => null,
                    'prev_verse' => null,
                ];
            }

            $interlinear = $this->buildInterlinear($verse->tokens);
            $commentaries = $this->commentaryService->forVerse($osisId)->take(10);

            $crossRefs = $this->findCrossReferences($verse->tokens, $osisId, 5);

            $next = $verse->nextVerse();
            $prev = $verse->previousVerse();

            return [
                'verse' => $verse->toArray(),
                'book' => $verse->book?->toArray(),
                'tokens' => $verse->tokens,
                'interlinear' => $interlinear,
                'commentaries' => $commentaries,
                'cross_references' => $crossRefs,
                'next_verse' => $next ? ['osis_id' => $next->osis_id, 'book_id' => $next->book_id, 'chapter' => $next->chapter, 'verse' => $next->verse] : null,
                'prev_verse' => $prev ? ['osis_id' => $prev->osis_id, 'book_id' => $prev->book_id, 'chapter' => $prev->chapter, 'verse' => $prev->verse] : null,
            ];
        };

        if ($storeSupportsTags) {
            return Cache::tags(['bible', 'verse_study'])->remember($cacheKey, 604800, $callback);
        }

        return Cache::remember($cacheKey, 604800, $callback);
    }

    /**
     * Build interlinear array from tokens (ordered).
     *
     * @param Collection<int, ArabicConcordance> $tokens
     * @return array<int, array{position:int, strong_id:string, source_word:string, transliteration:string, arabic_word:string, gloss_en:?string, morphology:?string, lexicon:?array}>
     */
    private function buildInterlinear(Collection $tokens): array
    {
        return $tokens->map(function (ArabicConcordance $tok): array {
            return [
                'position' => $tok->word_pos,
                'strong_id' => $tok->strong_id,
                'source_word' => $tok->source_word,
                'source_clean' => $tok->source_clean ?? null,
                'transliteration' => $tok->transliteration,
                'arabic_word' => $tok->arabic_word,
                'arabic_clean' => $tok->arabic_clean ?? null,
                'gloss_en' => $tok->gloss_en,
                'morphology' => $tok->morphology,
                'all_strongs' => $tok->all_strongs ?? null,
                'lexicon' => $tok->relationLoaded('lexicon') && $tok->lexicon ? [
                    'strong_id' => $tok->lexicon->strong_id,
                    'lemma' => $tok->lexicon->lemma,
                    'transliteration' => $tok->lexicon->transliteration,
                    'language' => $tok->lexicon->language,
                    'part_of_speech' => $tok->lexicon->part_of_speech ?? null,
                    'short_gloss_ar' => $tok->lexicon->short_gloss_ar ?? null,
                ] : null,
            ];
        })->toArray();
    }

    /**
     * Find cross-references: other verses sharing Strong IDs with current verse.
     *
     * @param Collection<int, ArabicConcordance> $tokens
     * @return Collection<int, Verse>
     */
    private function findCrossReferences(Collection $tokens, string $currentOsis, int $limit = 5): Collection
    {
        $strongIds = $tokens->pluck('strong_id')->unique()->filter()->take(10)->values()->toArray();

        if ($strongIds === []) {
            return new Collection();
        }

        // Find other verse_osis that share any of these Strong IDs, excluding current, ranked by shared count
        // We do a single aggregated query instead of N+1
        $relatedOsis = DB::table('arabic_concordance')
            ->select('verse_osis', DB::raw('COUNT(*) as shared'))
            ->whereIn('strong_id', $strongIds)
            ->where('verse_osis', '!=', $currentOsis)
            ->groupBy('verse_osis')
            ->orderByDesc('shared')
            ->orderBy('verse_osis')
            ->limit($limit)
            ->pluck('verse_osis');

        if ($relatedOsis->isEmpty()) {
            return new Collection();
        }

        return Verse::query()
            ->select(['id', 'book_id', 'chapter', 'verse', 'osis_id'])
            ->whereIn('osis_id', $relatedOsis->toArray())
            ->with('book:id,code,name_ar,name_en,slug')
            ->get()
            ->sortBy(function (Verse $v) use ($relatedOsis) {
                return array_search($v->osis_id, $relatedOsis->toArray(), true);
            })
            ->values();
    }

    private function chapterCacheKey(string $slug, int $chapter): string
    {
        // Normalized key: chapter_{slug}_{chapter}
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9_\-]/', '_', $slug) ?? $slug;

        return "chapter_{$slug}_{$chapter}";
    }

    /**
     * @return array{book:null, chapter:int, verses:Collection, verses_count:0, commentaries:Collection, next_chapter:null, prev_chapter:null}
     */
    private function emptyChapter(string $slug, int $chapter): array
    {
        return [
            'book' => null,
            'chapter' => $chapter,
            'verses' => new Collection(),
            'verses_count' => 0,
            'commentaries' => new Collection(),
            'next_chapter' => null,
            'prev_chapter' => null,
        ];
    }

    /**
     * Resolve adjacent chapter (book-aware, cross-book).
     *
     * @return array{book_slug:string, chapter:int, book_name_ar:string}|null
     */
    private function resolveAdjacentChapter(Book $book, int $current, int $direction): ?array
    {
        // Try same book next/prev chapter existence via verses table
        $candidate = $current + $direction;

        // Quick existence check: does verse exist for candidate chapter in same book?
        $exists = Verse::query()
            ->where('book_id', $book->id)
            ->where('chapter', $candidate)
            ->exists();

        if ($exists) {
            return [
                'book_slug' => $book->slug,
                'chapter' => $candidate,
                'book_name_ar' => $book->name_ar,
                'book_name_en' => $book->name_en,
            ];
        }

        // Cross-book: find next book with at least one verse
        $adjacentBook = Book::query()
            ->select(['id', 'slug', 'name_ar', 'name_en', 'book_number'])
            ->where('book_number', $direction > 0 ? '>' : '<', $book->book_number)
            ->orderBy('book_number', $direction > 0 ? 'asc' : 'desc')
            ->first();

        if ($adjacentBook === null) {
            return null;
        }

        // Find first/last chapter of adjacent book
        $targetChapter = $direction > 0
            ? Verse::where('book_id', $adjacentBook->id)->min('chapter')
            : Verse::where('book_id', $adjacentBook->id)->max('chapter');

        if ($targetChapter === null) {
            return null;
        }

        return [
            'book_slug' => $adjacentBook->slug,
            'chapter' => (int) $targetChapter,
            'book_name_ar' => $adjacentBook->name_ar,
            'book_name_en' => $adjacentBook->name_en,
        ];
    }
}
