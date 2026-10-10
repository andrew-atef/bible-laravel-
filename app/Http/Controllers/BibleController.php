<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Lexicon;
use App\Models\LxxConcordance;
use App\Models\Verse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class BibleController extends Controller
{
    /**
     * GET /api/chapter/{book_abbr}/{chapter_num}
     * React SPA contract — minified keys, strict schema only.
     */
    public function getChapter(string $book_abbr, int $chapter_num): JsonResponse
    {
        $bookAbbr = trim($book_abbr);
        $chapterNum = (int) $chapter_num;
        if ($bookAbbr === '' || $chapterNum < 1 || $chapterNum > 150) {
            return response()->json(['success' => false, 'message' => 'Invalid book or chapter'], 422);
        }
        $book = Book::findByIdentifier($bookAbbr);
        if ($book === null) {
            $book = Book::whereRaw('LOWER(code) = ?', [strtolower($bookAbbr)])->first();
        }
        if ($book === null) {
            return response()->json(['success' => false, 'message' => "Book '{$bookAbbr}' not found"], 404);
        }

        $verses = Verse::query()
            ->select(['id', 'book_id', 'chapter', 'verse', 'osis_id', 'text_ar', 'text_clean'])
            ->where('book_id', $book->id)
            ->where('chapter', $chapterNum)
            ->orderBy('verse')
            ->with([
                'tokens' => function ($q): void {
                    $q->select(['id', 'verse_osis', 'word_pos', 'strong_id', 'source_word', 'source_clean', 'transliteration', 'arabic_word', 'arabic_clean', 'gloss_en', 'morphology', 'morphology_ar', 'all_strongs'])
                        ->orderBy('word_pos');
                },
                'tokens.lexicon:strong_id,lemma,lemma_clean,transliteration,pronunciation,language,part_of_speech,word_origin,root_strongs,roots_json,strongs_definition,kjv_translations,outline_json,is_indexed',
            ])
            ->get();

        if ($verses->isEmpty()) {
            return response()->json(['success' => false, 'message' => "Chapter {$chapterNum} not found for {$book->name_ar}"], 404);
        }

        // Build vs array — React contract: v_id, num, text, w[{pos, ar, v_orig, orig, translit, morph_ar, str}]
        $vs = $verses->map(function (Verse $v): array {
            $w = $v->tokens->map(function ($tok): array {
                $lex = $tok->relationLoaded('lexicon') ? $tok->lexicon : null;
                // str: array of Strong IDs — from all_strongs if present else strong_id
                $str = [];
                if (!empty($tok->all_strongs)) {
                    $parts = preg_split('/[;,]/', $tok->all_strongs);
                    foreach ($parts as $p) {
                        $p = strtoupper(trim($p));
                        if (preg_match('/^[HG]\d+$/', $p)) $str[] = $p;
                    }
                    if (empty($str) && !empty($tok->strong_id)) $str = [strtoupper($tok->strong_id)];
                } elseif (!empty($tok->strong_id)) {
                    $str = [strtoupper($tok->strong_id)];
                }
                return [
                    'pos' => (int) $tok->word_pos,
                    'ar' => $tok->arabic_word,
                    'v_orig' => $tok->source_word,
                    'orig' => $lex?->lemma ?? $tok->source_word,
                    'translit' => $tok->transliteration,
                    'morph_ar' => $tok->morphology_ar,
                    'str' => $str,
                ];
            })->toArray();
            return [
                'v_id' => $v->osis_id,
                'num' => (int) $v->verse,
                'text' => $v->text_ar,
                'w' => $w,
            ];
        })->toArray();

        // Build dict — strict schema only, no phantom columns
        $strongIds = $verses->flatMap(fn (Verse $v) => $v->tokens->pluck('strong_id'))
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->map(fn ($v) => strtoupper(trim($v)))
            ->unique()->values()->toArray();

        $dict = [];
        if ($strongIds !== []) {
            $lexicons = Lexicon::query()
                ->select(['strong_id','language','lemma','lemma_clean','transliteration','pronunciation','part_of_speech','word_origin','root_strongs','roots_json','strongs_definition','kjv_translations','outline_json','is_indexed'])
                ->whereIn('strong_id', $strongIds)
                ->withCount('lxxConcordances')
                ->get()->keyBy('strong_id');

            $allRootIds = $lexicons->flatMap(fn (Lexicon $lex) => $lex->roots_json ?? [])
                ->filter(fn ($v) => is_string($v) && preg_match('/^[HG]\d+$/i', $v))
                ->map(fn ($v) => strtoupper($v))->unique()->values()->toArray();

            $rootMap = [];
            if ($allRootIds !== []) {
                $rootMap = Lexicon::whereIn('strong_id', $allRootIds)
                    ->select(['strong_id','lemma','transliteration','strongs_definition','kjv_translations'])
                    ->get()->keyBy('strong_id');
            }

            foreach ($strongIds as $sid) {
                $lex = $lexicons->get($sid);
                if ($lex === null) continue;
                $rootsJson = is_array($lex->roots_json) ? $lex->roots_json : [];
                $roots = [];
                foreach ($rootsJson as $rootId) {
                    if (!is_string($rootId) || $rootId==='') continue;
                    $rootId = strtoupper(trim($rootId));
                    $rootLex = $rootMap[$rootId] ?? null;
                    if ($rootLex instanceof Lexicon) {
                        $roots[] = [
                            'strong_id' => $rootLex->strong_id,
                            'lemma' => $rootLex->lemma,
                            'translit' => $rootLex->transliteration,
                            'arabic_gloss' => $rootLex->strongs_definition ? trim(explode(',', $rootLex->strongs_definition)[0]) : null,
                        ];
                    } else {
                        $roots[] = ['strong_id'=>$rootId,'lemma'=>null,'translit'=>null,'arabic_gloss'=>null];
                    }
                }
                $lxxCount = (int) ($lex->lxx_concordances_count ?? $lex->lxxConcordances()->count());
                // Task 1: For Arabic translations, read directly from strongs_definition or fallback cleanly
                $defOrig = $lex->strongs_definition ?? '';
                $defAr = $defOrig; // strict schema fallback — no phantom strongs_definition_ar
                // g: try kjv_translations first, then strongs_definition first word
                $g = $lex->kjv_translations ? trim(explode(',', $lex->kjv_translations)[0]) : (trim(explode(';', $defOrig)[0]) ?: '');
                $dict[$sid] = [
                    'g' => $g,
                    'lemma' => $lex->lemma,
                    'translit' => $lex->transliteration,
                    'def_orig' => $defOrig,
                    'def_ar' => $defAr,
                    'roots' => $roots,
                    'lxx_count' => $lxxCount,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'b' => ['n' => $book->name_ar, 'a' => $book->code],
                'c' => $chapterNum,
                'vs' => $vs,
                'dict' => $dict,
            ],
        ]);
    }

    public function getLxxOccurrences(string $strongId): JsonResponse
    {
        $strongId = strtoupper(trim($strongId));
        if (! preg_match('/^[HG]\d+$/', $strongId)) {
            return response()->json(['success' => false, 'message' => 'Invalid Strong ID. Use H7225 or G4647'], 422);
        }
        $lexExists = Lexicon::where('strong_id', $strongId)->exists();
        if (! $lexExists) {
            return response()->json(['success' => false, 'message' => "Strong {$strongId} not found"], 404);
        }
        $cacheKey = "lxx_{$strongId}";
        $data = Cache::remember($cacheKey, 86400, function () use ($strongId): array {
            $rows = LxxConcordance::query()
                ->join('verses', 'verses.osis_id', '=', 'lxx_concordance.verse_osis')
                ->join('books', 'books.id', '=', 'verses.book_id')
                ->where('lxx_concordance.strong_id', $strongId)
                ->select([
                    'lxx_concordance.verse_osis',
                    'lxx_concordance.hebrew_word',
                    'lxx_concordance.context_note',
                    'verses.text_ar',
                    'verses.chapter',
                    'verses.verse',
                    'books.code as book_code',
                    'books.name_ar as book_name_ar',
                ])
                ->orderBy('books.id')
                ->orderBy('verses.chapter')
                ->orderBy('verses.verse')
                ->get();
            $occurrences = $rows->map(function ($row): array {
                $osis = (string) $row->verse_osis;
                $bookCode = (string) ($row->book_code ?? '');
                $chapter = (int) ($row->chapter ?? 0);
                $verseNum = (int) ($row->verse ?? 0);
                if ($bookCode === '' || $chapter === 0) {
                    $parts = explode('.', $osis);
                    $bookCode = $parts[0] ?? $bookCode;
                    $chapter = isset($parts[1]) ? (int) $parts[1] : $chapter;
                    $verseNum = isset($parts[2]) ? (int) $parts[2] : $verseNum;
                }
                $bookNameAr = $row->book_name_ar ?? $bookCode;
                $refAr = $this->formatArabicReference($bookNameAr, $chapter, $verseNum);
                return [
                    'osis' => $osis,
                    'ref_ar' => $refAr,
                    'book_code' => $bookCode,
                    'chapter' => $chapter,
                    'verse' => $verseNum,
                    'hebrew_word' => $row->hebrew_word,
                    'context_note' => $row->context_note,
                    'text_ar' => $row->text_ar,
                ];
            })->toArray();
            return ['count' => count($occurrences), 'occurrences' => $occurrences];
        });
        return response()->json(['success' => true, 'strong_id' => $strongId, 'count' => $data['count'], 'occurrences' => $data['occurrences']]);
    }

    private function formatArabicReference(string $bookNameAr, int $chapter, int $verse): string
    {
        $chAr = $this->toEasternArabic((string) $chapter);
        $vAr = $this->toEasternArabic((string) $verse);
        return trim($bookNameAr) . ' ' . $chAr . ':' . $vAr;
    }

    private function toEasternArabic(string $western): string
    {
        return strtr($western, ['0'=>'٠','1'=>'١','2'=>'٢','3'=>'٣','4'=>'٤','5'=>'٥','6'=>'٦','7'=>'٧','8'=>'٨','9'=>'٩']);
    }
}
