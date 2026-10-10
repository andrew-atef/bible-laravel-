<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Biblical\ArabicScriptService;
use App\Services\Biblical\LexiconStudyService;
use App\Services\Seo\SeoMetaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * LexiconController — /lexicon/{strong_id}
 *
 * BibleHub-style Lexical Summary + Englishman's Arabic Concordance + DefinedTerm JSON-LD.
 */
class LexiconController extends Controller
{
    public function __construct(
        private readonly LexiconStudyService $lexiconStudy,
        private readonly SeoMetaService $seoMeta,
        private readonly ArabicScriptService $arabicScript
    ) {}

    /**
     * Show lexicon entry and concordance.
     *
     * @param string $strong_id e.g. H7225 or G2424 (case-insensitive)
     */
    public function show(Request $request, string $strong_id): View
    {
        $strongId = strtoupper(trim($strong_id));

        if (! preg_match('/^([HG])(\d+)$/', $strongId, $m)) {
            abort(404, "رقم سترونغ غير صحيح: {$strong_id}. استخدم مثل H410 أو G2424");
        }

        $prefix = $m[1]; // H or G
        $num = (int) $m[2];

        // حساب الكلمة السابقة والتالية للتنقل مثل BibleHub
        $prevStrong = $num > 1 ? $prefix . ($num - 1) : null;
        $maxNum = ($prefix === 'H') ? 8674 : 5624;
        $nextStrong = $num < $maxNum ? $prefix . ($num + 1) : null;

        $summary = $this->lexiconStudy->getLexiconSummary($strongId);

        if ($summary === null) {
            abort(404, "المدخل المعجمي {$strongId} غير موجود");
        }

        // Paginated concordance
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('perPage', 30);
        $perPage = max(5, min(50, $perPage));

        $occurrences = $this->lexiconStudy->getConcordanceOccurrences($strongId, $page, $perPage);

        // SEO — DefinedTerm
        $seo = $this->seoMeta->forLexicon($summary);
        $breadcrumbs = $seo['breadcrumbs'];

        $currentEntity = \App\Models\Lexicon::where('strong_id', $strongId)->first() ?? $strongId;

        // Septuagint cross-references — LXX concordance (strict schema: books.id)
        $lxxOccurrences = \App\Models\LxxConcordance::query()
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
                'books.name_ar as book_name_ar'
            ])
            ->orderBy('books.id')
            ->orderBy('verses.chapter')
            ->orderBy('verses.verse')
            ->get();

        return view('lexicon.show', compact(
            'summary', 
            'occurrences', 
            'seo', 
            'breadcrumbs', 
            'strongId', 
            'prevStrong', 
            'nextStrong', 
            'currentEntity',
            'lxxOccurrences'
        ));
    }
}
