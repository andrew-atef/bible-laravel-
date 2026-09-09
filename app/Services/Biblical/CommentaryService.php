<?php

declare(strict_types=1);

namespace App\Services\Biblical;

use App\Models\Book;
use App\Models\Commentary;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * CommentaryService — theological commentary integration engine
 *
 * Handles 3 querying modes:
 *  - Single Verse:  verse_start = :osis AND verse_end IS NULL
 *  - Passage Range: target verse falls between verse_start and verse_end (canonical ordering)
 *  - Word/Phrase Level: strong_id = :strong OR target_phrase = :phrase
 */
class CommentaryService
{
    /**
     * Get commentaries covering a single verse (exact + passage ranges).
     *
     * Merges:
     *  - verse_start = :osis (single verse)
     *  - passage where :osis BETWEEN verse_start AND verse_end (canonical)
     */
    public function forVerse(string $osis): Collection
    {
        $osis = trim($osis);

        if ($osis === '') {
            return new Collection();
        }

        $cacheKey = 'commentary_for_verse_' . str_replace('.', '_', $osis);
        $supportsTags = method_exists(Cache::getStore(), 'tags');
        $callback = function () use ($osis): Collection {
            // Single-verse exact matches (fast indexed) — handle both NULL and self-range (verse_end = verse_start)
            $exact = Commentary::query()
                ->where('verse_start', $osis)
                ->where(function ($q) use ($osis): void {
                    $q->whereNull('verse_end')->orWhere('verse_end', $osis);
                })
                ->orderByRaw("CASE level WHEN 'word' THEN 1 WHEN 'verse' THEN 2 WHEN 'passage' THEN 3 ELSE 4 END")
                ->get();

            // Passage ranges that contain this osis (need canonical ordering, not lexical)
            $passageCandidates = Commentary::query()
                ->whereNotNull('verse_end')
                ->where('level', 'passage')
                ->get()
                ->filter(fn (Commentary $c) => $this->osisInRange($osis, $c->verse_start, $c->verse_end));

            $merged = $exact->merge($passageCandidates)->unique('id')->sortBy(fn (Commentary $c) => $this->levelPriority($c->level))->values();

            return $merged;
        };

        if ($supportsTags) {
            return Cache::tags(['commentaries', 'bible'])->remember($cacheKey, 604800, $callback);
        }

        return Cache::remember($cacheKey, 604800, $callback);
    }

    /**
     * Get commentaries where strong_id equals selected word.
     */
    public function forStrong(string $strongId): Collection
    {
        $strongId = strtoupper(trim($strongId));

        if (! preg_match('/^[HG]\d+$/', $strongId)) {
            return new Collection();
        }

        $cacheKey = 'commentary_for_strong_' . $strongId;
        $supportsTags = method_exists(Cache::getStore(), 'tags');
        $callback = fn (): Collection => Commentary::forStrong($strongId)
            ->orderByRaw("CASE level WHEN 'word' THEN 1 WHEN 'verse' THEN 2 WHEN 'passage' THEN 3 ELSE 4 END")
            ->get();

        if ($supportsTags) {
            return Cache::tags(['commentaries'])->remember($cacheKey, 604800, $callback);
        }

        return Cache::remember($cacheKey, 604800, $callback);
    }

    /**
     * Get commentaries where target_phrase equals selected phrase (Arabic word/clause).
     */
    public function forPhrase(string $phrase): Collection
    {
        $phrase = trim($phrase);

        if ($phrase === '') {
            return new Collection();
        }

        // Exact + normalized fallback via ArabicScriptService? For now exact trimmed
        return Commentary::query()
            ->where('target_phrase', $phrase)
            ->orWhere('target_phrase', trim($phrase))
            ->get();
    }

    /**
     * Unified word-level: strong_id OR target_phrase
     */
    public function forWord(string $strongId, string $phrase = ''): Collection
    {
        $strongId = strtoupper(trim($strongId));
        $phrase = trim($phrase);

        $query = Commentary::query()->where(function ($q) use ($strongId, $phrase): void {
            if (preg_match('/^[HG]\d+$/', $strongId)) {
                $q->where('strong_id', $strongId);
            }
            if ($phrase !== '') {
                $q->orWhere('target_phrase', $phrase);
            }
        })->where('level', 'word');

        return $query->orderBy('verse_start')->get();
    }

    /**
     * Get commentaries covering a passage range (e.g. Jhn.1.1 to Jhn.1.5)
     */
    public function forPassage(string $startOsis, string $endOsis): Collection
    {
        $startOsis = trim($startOsis);
        $endOsis = trim($endOsis);

        // Direct matches where verse_start/end exactly equals range
        $exact = Commentary::query()
            ->where('verse_start', $startOsis)
            ->where('verse_end', $endOsis)
            ->get();

        // Also any commentary that overlaps the range (our range contains theirs or vice versa)
        // Simplified: where their range intersects queried range
        $overlapping = Commentary::query()
            ->whereNotNull('verse_end')
            ->get()
            ->filter(fn (Commentary $c) => $this->rangesOverlap($startOsis, $endOsis, $c->verse_start, $c->verse_end));

        return $exact->merge($overlapping)->unique('id')->values();
    }

    /**
     * Check if target osis falls within [start, end] canonical ordering.
     */
    public function osisInRange(string $target, string $start, string $end): bool
    {
        $t = $this->osisToKey($target);
        $s = $this->osisToKey($start);
        $e = $this->osisToKey($end);

        if ($t === null || $s === null || $e === null) {
            // Fallback lexical
            return $target >= $start && $target <= $end;
        }

        return $t >= $s && $t <= $e;
    }

    public function rangesOverlap(string $aStart, string $aEnd, string $bStart, string $bEnd): bool
    {
        $aS = $this->osisToKey($aStart);
        $aE = $this->osisToKey($aEnd);
        $bS = $this->osisToKey($bStart);
        $bE = $this->osisToKey($bEnd);

        if ($aS === null || $aE === null || $bS === null || $bE === null) {
            return $aStart <= $bEnd && $bStart <= $aEnd;
        }

        return $aS <= $bE && $bS <= $aE;
    }

    /**
     * Convert OSIS (e.g. Gen.1.1) to canonical sortable integer: book_number*1M + chapter*1000 + verse
     */
    public function osisToKey(string $osis): ?int
    {
        $parts = explode('.', trim($osis));

        if (count($parts) !== 3) {
            return null;
        }

        [$code, $ch, $v] = $parts;

        // Use cached book_number lookup
        static $codeToNumber = null;

        if ($codeToNumber === null) {
            try {
                $codeToNumber = Book::pluck('book_number', 'code')->toArray();
            } catch (\Throwable $e) {
                $codeToNumber = [];
            }
        }

        $bookNum = $codeToNumber[$code] ?? null;

        if ($bookNum === null) {
            return null;
        }

        $ch = (int) $ch;
        $v = (int) $v;

        return $bookNum * 1000000 + $ch * 1000 + $v;
    }

    private function levelPriority(string $level): int
    {
        return match ($level) {
            'word' => 1,
            'verse' => 2,
            'passage' => 3,
            default => 4,
        };
    }
}
