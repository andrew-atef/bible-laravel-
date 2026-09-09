<?php

declare(strict_types=1);

namespace App\Services\Biblical;

use App\Models\ArabicConcordance;
use App\Models\Book;
use App\Models\Lexicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SearchService — unified smart search across 3 intents
 *
 * 1. Scripture Reference Search: تكوين 1, Gen 1:1, يوحنا 3:16, Jhn.1.1 => redirect
 * 2. Normalized Arabic Full-Text: arabic_clean (Tashkeel/Alef/Yaa tolerant)
 * 3. Lexical Strong & Transliteration: H7225, G3056, רֵאשִׁית, λόγος, Tohu, Logos, Bereshit
 */
class SearchService
{
    public function __construct(
        private readonly ArabicScriptService $arabicScript
    ) {}

    /**
     * Detect scripture reference and return redirect payload, or null if not a reference.
     *
     * Supports:
     *  - OSIS direct: Gen.1.1, 1Sa.15.21
     *  - English codes: Gen 1:1, Genesis 1:1, John 3:16, 1 John 1:1
     *  - Arabic: تكوين 1, التكوين 1:1, يوحنا 3:16, صموئيل الأول 15:21, رؤيا 1
     *
     * @return array{book:Book, chapter:int, verse:?int, redirect:string, osis:?string}|null
     */
    public function detectReference(string $query): ?array
    {
        $q = trim($query);
        if ($q === '') {
            return null;
        }

        // Normalize spaces/colon variants: allow "3 : 16", "3:16", "3.16"
        $qNormalizedSpaces = (string) preg_replace('/\s*[.：:]\s*/u', ':', $q);
        $qNormalizedSpaces = (string) preg_replace('/\s+/u', ' ', $qNormalizedSpaces);
        $qNormalizedSpaces = trim($qNormalizedSpaces);

        // 1. OSIS direct: Gen.1.1, 1Sa.15.21, Jhn.1.1
        if (preg_match('/^([1-3]?[A-Za-z]{2,3})\.(\d+)\.(\d+)$/', $q, $m)) {
            $code = $m[1];
            $ch = (int) $m[2];
            $v = (int) $m[3];
            $book = $this->findBookByCode($code);
            if ($book) {
                return [
                    'book' => $book,
                    'chapter' => $ch,
                    'verse' => $v,
                    'osis' => "{$book->code}.{$ch}.{$v}",
                    'redirect' => url("/verse/{$book->code}.{$ch}.{$v}"),
                ];
            }
        }

        // 2. English reference: "Gen 1:1", "Genesis 1", "1 John 1:1", "Rev 22:21"
        // Pattern: [1-3]? BookName  chapter [:verse]
        if (preg_match('/^([1-3]?\s*[A-Za-z]{2,}(?:\s+[A-Za-z]+)?)\s+(\d+)(?::(\d+))?\s*$/', $q, $m)) {
            $bookPart = trim($m[1]);
            $ch = (int) $m[2];
            $v = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null;
            $book = $this->findBookByEnglishName($bookPart);
            if ($book) {
                if ($v !== null) {
                    return [
                        'book' => $book,
                        'chapter' => $ch,
                        'verse' => $v,
                        'osis' => "{$book->code}.{$ch}.{$v}",
                        'redirect' => url("/verse/{$book->code}.{$ch}.{$v}"),
                    ];
                }
                return [
                    'book' => $book,
                    'chapter' => $ch,
                    'verse' => null,
                    'osis' => "{$book->code}.{$ch}.1",
                    'redirect' => url("/read/{$book->slug}/{$ch}"),
                ];
            }
        }

        // 3. Also try with colon already normalized: "تكوين 1:1" Arabic with English digits
        // For Arabic, we need to capture Arabic book name + numbers
        // Use unicode-aware pattern: book part is Arabic letters/spaces, then digits
        if (preg_match('/^([^\d]+?)\s+(\d+)(?::(\d+))?\s*$/u', $qNormalizedSpaces, $m)) {
            $bookPartRaw = trim($m[1]);
            $ch = (int) $m[2];
            $v = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null;

            // Only consider if bookPart contains Arabic (or we already tried English)
            if (preg_match('/[\x{0600}-\x{06FF}]/u', $bookPartRaw)) {
                $book = $this->findBookByArabicName($bookPartRaw);
                if ($book) {
                    if ($v !== null) {
                        return [
                            'book' => $book,
                            'chapter' => $ch,
                            'verse' => $v,
                            'osis' => "{$book->code}.{$ch}.{$v}",
                            'redirect' => url("/verse/{$book->code}.{$ch}.{$v}"),
                        ];
                    }
                    return [
                        'book' => $book,
                        'chapter' => $ch,
                        'verse' => null,
                        'osis' => "{$book->code}.{$ch}.1",
                        'redirect' => url("/read/{$book->slug}/{$ch}"),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Unified search — returns grouped results for view.
     *
     * @return array{
     *   intent:string, // reference|unified|empty
     *   redirect?:string,
     *   book?:Book,
     *   lexicons:Collection,
     *   verses:LengthAwarePaginator,
     *   total:int,
     *   query:string,
     *   normalized:string
     * }
     */
    public function search(string $query, int $page = 1, int $perPage = 20): array
    {
        $q = trim($query);
        $normalized = $this->arabicScript->normalizeForSearch($q);

        if ($q === '') {
            return [
                'intent' => 'empty',
                'lexicons' => new Collection(),
                'verses' => new ConcretePaginator([], 0, $perPage, $page),
                'total' => 0,
                'query' => $q,
                'normalized' => $normalized,
            ];
        }

        // 1. Reference detection first (highest priority)
        $ref = $this->detectReference($q);
        if ($ref !== null) {
            return [
                'intent' => 'reference',
                'redirect' => $ref['redirect'],
                'book' => $ref['book'],
                'chapter' => $ref['chapter'],
                'verse' => $ref['verse'],
                'osis' => $ref['osis'],
                'lexicons' => new Collection(),
                'verses' => new ConcretePaginator([], 0, $perPage, $page),
                'total' => 0,
                'query' => $q,
                'normalized' => $normalized,
            ];
        }

        // 2. Strong ID exact (if query is H/G + digits, treat as lexical first)
        $isStrong = (bool) preg_match('/^[HG]\d+$/i', $q);

        // Lexicons: search by Strong ID, lemma, lemma_clean, transliteration, strongs_definition
        $lexicons = $this->searchLexicons($q, $normalized);

        // Verses: normalized Arabic + transliteration
        $verses = $this->searchVerses($q, $normalized, $page, $perPage);

        $total = $lexicons->count() + $verses->total();

        return [
            'intent' => 'unified',
            'lexicons' => $lexicons,
            'verses' => $verses,
            'total' => $total,
            'query' => $q,
            'normalized' => $normalized,
        ];
    }

    /**
     * Search lexicons by Strong ID, lemma, transliteration, definition.
     */
    public function searchLexicons(string $q, ?string $normalized = null): Collection
    {
        $q = trim($q);
        $normalized ??= $this->arabicScript->normalizeForSearch($q);
        $qUpper = strtoupper($q);
        $qLower = mb_strtolower($q);

        // Strong ID exact
        if (preg_match('/^[HG]\d+$/i', $q)) {
            $exact = Lexicon::query()
                ->where('strong_id', $qUpper)
                ->select(['strong_id', 'lemma', 'lemma_clean', 'transliteration', 'pronunciation', 'language', 'part_of_speech', 'strongs_definition', 'kjv_translations'])
                ->get();
            if ($exact->isNotEmpty()) {
                return $exact;
            }
        }

        // Broad lexical search (limit 15 for view grouping)
        return Lexicon::query()
            ->select(['strong_id', 'lemma', 'lemma_clean', 'transliteration', 'pronunciation', 'language', 'part_of_speech', 'strongs_definition', 'kjv_translations'])
            ->where(function ($w) use ($q, $qLower, $normalized): void {
                $w->where('strong_id', 'like', "%{$q}%")
                    ->orWhere('lemma', 'like', "%{$q}%")
                    ->orWhere('lemma_clean', 'like', "%{$q}%")
                    ->orWhere('lemma_clean', 'like', "%{$normalized}%")
                    ->orWhere('transliteration', 'like', "%{$q}%")
                    ->orWhere('transliteration', 'like', "%{$qLower}%")
                    ->orWhere('pronunciation', 'like', "%{$q}%")
                    ->orWhere('strongs_definition', 'like', "%{$q}%")
                    ->orWhere('kjv_translations', 'like', "%{$q}%");
            })
            ->orderByRaw("CASE WHEN strong_id = ? THEN 0 WHEN transliteration LIKE ? THEN 1 ELSE 2 END", [$qUpper, "%{$q}%"])
            ->limit(15)
            ->get();
    }

    /**
     * البحث الذكي في نصوص الآيات (يدعم الجمل الكاملة والكلمات المتعددة)
     */
    public function searchVerses(string $q, ?string $normalized = null, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $q = trim($q);
        $normalized ??= $this->arabicScript->normalizeForSearch($q);
        $perPage = max(1, min(50, $perPage));
        $page = max(1, $page);

        // تفكيك الكلمات للبحث عن الجمل المركبة
        $words = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        // الاستعلام من جدول الآيات الكامل مع ربط اسم السفر
        $query = \App\Models\Verse::query()
            ->select(['id', 'book_id', 'chapter', 'verse', 'osis_id', 'text_ar', 'text_clean'])
            ->with('book:id,code,name_ar,name_en,slug');

        $hasArabic = (bool) preg_match('/[\x{0600}-\x{06FF}]/u', $q);

        if ($hasArabic) {
            $query->where(function ($w) use ($normalized, $words, $q): void {
                // 1. أولاً: مطابقة العبارة كاملة كجملة متصلة (أعلى دقة)
                $w->where('text_clean', 'like', "%{$normalized}%")
                  ->orWhere('text_ar', 'like', "%{$q}%");

                // 2. ثانياً: إذا كانت جملة، تحقق من وجود كل الكلمات في الآية
                if (count($words) > 1) {
                    $w->orWhere(function ($sub) use ($words): void {
                        foreach ($words as $word) {
                            $sub->where('text_clean', 'like', "%{$word}%");
                        }
                    });
                }
            });
        } else {
            // بحث بالحروف اللاتينية أو النطق
            $query->whereHas('tokens', function ($t) use ($q): void {
                $t->where('transliteration', 'like', "%{$q}%")
                  ->orWhere('gloss_en', 'like', "%{$q}%");
            });
        }

        // ترتيب النتائج حسب الأصحاح والشاهد
        $query->orderBy('book_id')->orderBy('chapter')->orderBy('verse');

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    private function findBookByCode(string $code): ?Book
    {
        $code = trim($code, '. ');
        // Handle codes like "1Jn" vs "1 John" (remove space)
        $codeNoSpace = str_replace(' ', '', $code);

        // Try exact code (case-insensitive)
        $book = Book::whereRaw('LOWER(code) = ?', [strtolower($codeNoSpace)])->first();
        if ($book) {
            return $book;
        }

        // Try slug
        $book = Book::whereRaw('LOWER(slug) = ?', [strtolower($codeNoSpace)])->first();
        if ($book) {
            return $book;
        }

        // Try English name mapping for common abbreviations (Gen -> Genesis)
        $map = [
            'gen' => 'Gen', 'ge' => 'Gen', 'gene' => 'Gen', 'genesis' => 'Gen',
            'exo' => 'Exo', 'ex' => 'Exo', 'exodus' => 'Exo',
            'lev' => 'Lev', 'leviticus' => 'Lev',
            'num' => 'Num', 'numbers' => 'Num',
            'deu' => 'Deu', 'deut' => 'Deu', 'deuteronomy' => 'Deu',
            'jos' => 'Jos', 'josh' => 'Jos', 'joshua' => 'Jos',
            'jdg' => 'Jdg', 'judg' => 'Jdg', 'judges' => 'Jdg',
            'rut' => 'Rut', 'ruth' => 'Rut',
            '1sa' => '1Sa', '1sam' => '1Sa', '1samuel' => '1Sa',
            '2sa' => '2Sa', '2sam' => '2Sa',
            '1ki' => '1Ki', '1kings' => '1Ki',
            '2ki' => '2Ki',
            '1ch' => '1Ch', '1chr' => '1Ch', '1chronicles' => '1Ch',
            '2ch' => '2Ch',
            'ezr' => 'Ezr', 'ezra' => 'Ezr',
            'neh' => 'Neh', 'nehemiah' => 'Neh',
            'est' => 'Est', 'esther' => 'Est',
            'job' => 'Job',
            'psa' => 'Psa', 'ps' => 'Psa', 'psalm' => 'Psa', 'psalms' => 'Psa',
            'pro' => 'Pro', 'prov' => 'Pro', 'proverbs' => 'Pro',
            'ecc' => 'Ecc', 'eccl' => 'Ecc', 'ecclesiastes' => 'Ecc',
            'sng' => 'Sng', 'song' => 'Sng', 'songs' => 'Sng',
            'isa' => 'Isa', 'isaiah' => 'Isa',
            'jer' => 'Jer', 'jeremiah' => 'Jer',
            'lam' => 'Lam', 'lamentations' => 'Lam',
            'ezk' => 'Ezk', 'ezek' => 'Ezk', 'ezekiel' => 'Ezk',
            'dan' => 'Dan', 'daniel' => 'Dan',
            'hos' => 'Hos', 'hosea' => 'Hos',
            'jol' => 'Jol', 'joel' => 'Jol',
            'amo' => 'Amo', 'amos' => 'Amo',
            'oba' => 'Oba', 'obadiah' => 'Oba',
            'jon' => 'Jon', 'jonah' => 'Jon',
            'mic' => 'Mic', 'micah' => 'Mic',
            'nam' => 'Nam', 'nahum' => 'Nam',
            'hab' => 'Hab', 'habakkuk' => 'Hab',
            'zep' => 'Zep', 'zephaniah' => 'Zep',
            'hag' => 'Hag', 'haggai' => 'Hag',
            'zec' => 'Zec', 'zechariah' => 'Zec',
            'mal' => 'Mal', 'malachi' => 'Mal',
            'mat' => 'Mat', 'matt' => 'Mat', 'matthew' => 'Mat',
            'mrk' => 'Mrk', 'mark' => 'Mrk',
            'luk' => 'Luk', 'luke' => 'Luk',
            'jhn' => 'Jhn', 'john' => 'Jhn', 'jn' => 'Jhn',
            'act' => 'Act', 'acts' => 'Act',
            'rom' => 'Rom', 'romans' => 'Rom',
            '1co' => '1Co', '1cor' => '1Co', '1corinthians' => '1Co',
            '2co' => '2Co',
            'gal' => 'Gal', 'galatians' => 'Gal',
            'eph' => 'Eph', 'ephesians' => 'Eph',
            'php' => 'Php', 'phil' => 'Php', 'philippians' => 'Php',
            'col' => 'Col', 'colossians' => 'Col',
            '1th' => '1Th', '1thess' => '1Th',
            '2th' => '2Th',
            '1ti' => '1Ti', '1tim' => '1Ti',
            '2ti' => '2Ti',
            'tit' => 'Tit', 'titus' => 'Tit',
            'phm' => 'Phm', 'philemon' => 'Phm',
            'heb' => 'Heb', 'hebrews' => 'Heb',
            'jas' => 'Jas', 'james' => 'Jas',
            '1pe' => '1Pe', '1pet' => '1Pe',
            '2pe' => '2Pe',
            '1jn' => '1Jn', '1john' => '1Jn',
            '2jn' => '2Jn',
            '3jn' => '3Jn',
            'jud' => 'Jud', 'jude' => 'Jud',
            'rev' => 'Rev', 'revelation' => 'Rev',
        ];

        $key = strtolower(str_replace(' ', '', $code));
        if (isset($map[$key])) {
            return Book::where('code', $map[$key])->first();
        }

        return null;
    }

    private function findBookByEnglishName(string $name): ?Book
    {
        $name = trim($name);
        $lower = mb_strtolower($name);

        // Try code first
        $book = $this->findBookByCode($name);
        if ($book) {
            return $book;
        }

        // Try name_en direct
        $book = Book::whereRaw('LOWER(name_en) = ?', [$lower])->first();
        if ($book) {
            return $book;
        }

        // Try LIKE for partial (Genesis contains Gen)
        $book = Book::whereRaw('LOWER(name_en) LIKE ?', ["%{$lower}%"])->first();

        return $book;
    }

    private function findBookByArabicName(string $nameRaw): ?Book
    {
        $raw = trim($nameRaw);
        $normalizedRaw = $this->arabicScript->normalizeForSearch($raw);
        $rawNoAl = preg_replace('/^ال/u', '', $raw);
        $normalizedNoAl = $this->arabicScript->normalizeForSearch($rawNoAl ?? $raw);

        // Order by length desc to match longest first (صموئيل الأول before صموئيل)
        $books = Book::orderByRaw('LENGTH(name_ar) DESC')->get(['code','slug','name_ar','name_en']);

        foreach ($books as $book) {
            $nameArNorm = $this->arabicScript->normalizeForSearch($book->name_ar);
            $nameArNoAl = preg_replace('/^ال/u', '', $book->name_ar);
            $nameArNoAlNorm = $this->arabicScript->normalizeForSearch($nameArNoAl ?? $book->name_ar);

            // Exact normalized match or without ال
            if ($normalizedRaw === $nameArNorm || $normalizedNoAl === $nameArNoAlNorm) {
                return $book;
            }
            // Also try raw without normalization (for exact Tashkeel)
            if ($raw === $book->name_ar || $rawNoAl === $nameArNoAl) {
                return $book;
            }
            // Try that raw starts with book name (for cases like "التكوين 1" where we already stripped chapter)
            // But caller already stripped chapter, so bookPart should equal name
            // Also handle that query may be "تكوين" without ال, match "التكوين"
            if ($normalizedRaw === $nameArNoAlNorm) {
                return $book;
            }
        }

        // Fallback: LIKE search on normalized
        foreach ($books as $book) {
            $nameArNorm = $this->arabicScript->normalizeForSearch($book->name_ar);
            if (str_contains($nameArNorm, $normalizedRaw) || str_contains($normalizedRaw, $nameArNorm)) {
                // Ensure length close (avoid matching "يوحنا" to "1 يوحنا" incorrectly — but we already ordered longest first)
                if (abs(mb_strlen($nameArNorm) - mb_strlen($normalizedRaw)) <= 3) {
                    return $book;
                }
            }
        }

        return null;
    }
}
