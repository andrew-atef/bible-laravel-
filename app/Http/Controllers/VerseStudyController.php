<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Biblical\BibleReaderService;
use App\Services\Seo\SeoMetaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * VerseStudyController — /verse/{osis}
 *
 * Deep study page with interlinear, commentaries, cross-refs, breadcrumbs, ScholarlyArticle JSON-LD.
 */
class VerseStudyController extends Controller
{
    public function __construct(
        private readonly BibleReaderService $bibleReader,
        private readonly SeoMetaService $seoMeta
    ) {}

    /**
     * Show verse study page.
     *
     * @param string $osis e.g. Gen.1.1 or 1Sa.15.21 — URL encoded, dots preserved
     */
    public function show(Request $request, string $osis): View
    {
        // Strict OSIS validation: Book.Chapter.Verse
        // Book: 1-3 letters + optional digit prefix (1Sa, 2Co), then .digits.digits
        $osis = trim($osis);
        if (! preg_match('/^[1-3]?[A-Za-z]{2,3}\.\d+\.\d+$/', $osis)) {
            abort(404, "صيغة الشاهد غير صحيحة: {$osis}. استخدم مثل Gen.1.1");
        }

        // Fetch study payload (cached 86400s)
        $study = $this->bibleReader->getVerseStudy($osis);

        // 404 if verse not found (tokens empty + verse null)
        if ($study['verse'] === null && $study['tokens']->isEmpty()) {
            abort(404, "الآية {$osis} غير موجودة");
        }

        // SEO — Schema.org ScholarlyArticle + BreadcrumbList
        $seo = $this->seoMeta->forVerse($study);

        $breadcrumbs = $seo['breadcrumbs'];

        // Interlinear already built by service (array of token maps with lexicon)
        $interlinear = $study['interlinear'];
        $tokens = $study['tokens'];
        $commentaries = $study['commentaries'];
        $crossReferences = $study['cross_references'];
        $verse = $study['verse'];
        $book = $study['book'];
        $nextVerse = $study['next_verse'];
        $prevVerse = $study['prev_verse'];

        // Gatekeeper: currentEntity for robots meta — use Verse model if exists, else OSIS string
        $currentEntity = null;
        if (! empty($verse) && isset($verse['osis_id'])) {
            $currentEntity = \App\Models\Verse::where('osis_id', $osis)->first();
            if (! $currentEntity) {
                $currentEntity = $osis; // fallback to OSIS string handled by gatekeeper
            }
        } else {
            $currentEntity = $osis;
        }

        // Preferred modern view: bible.verse-study with accordions A/B/C
        if (view()->exists('bible.verse-study')) {
            return view('bible.verse-study', compact(
                'osis',
                'verse',
                'book',
                'tokens',
                'interlinear',
                'commentaries',
                'crossReferences',
                'nextVerse',
                'prevVerse',
                'seo',
                'breadcrumbs',
                'currentEntity'
            ));
        }

        return view('verse.study', compact(
            'osis',
            'verse',
            'book',
            'tokens',
            'interlinear',
            'commentaries',
            'crossReferences',
            'nextVerse',
            'prevVerse',
            'seo',
            'breadcrumbs',
            'currentEntity'
        ));
    }
}
