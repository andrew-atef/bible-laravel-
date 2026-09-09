<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Biblical\SearchService;
use App\Services\Seo\SeoMetaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SearchController — /search?q=...
 *
 * Delegates to SearchService for 3 intents:
 *  - Scripture Reference Search (redirect)
 *  - Normalized Arabic Full-Text
 *  - Lexical Strong & Transliteration
 */
class SearchController extends Controller
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly SeoMetaService $seoMeta
    ) {}

    /**
     * @return View|RedirectResponse
     */
    public function query(Request $request): View|RedirectResponse
    {
        $rawQ = (string) $request->query('q', '');
        $q = trim($rawQ);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;

        // Empty search — show form
        if ($q === '') {
            $seo = $this->seoMeta->forSearch('', 0);
            $breadcrumbs = [
                ['name' => 'الرئيسية', 'url' => url('/')],
                ['name' => 'البحث', 'url' => url('/search')],
            ];

            return view('search.results', [
                'q' => '',
                'rawQ' => '',
                'mode' => 'empty',
                'results' => null,
                'verses' => null,
                'lexicons' => collect(),
                'seo' => $seo,
                'breadcrumbs' => $breadcrumbs,
                'total' => 0,
                'lexicon' => null,
            ]);
        }

        $result = $this->searchService->search($q, $page, $perPage);

        // Intent 1: Scripture Reference => 302 redirect to reader/verse
        if ($result['intent'] === 'reference') {
            // For UX, we redirect directly. Preserve q for analytics? No.
            return redirect($result['redirect'], 302);
        }

        // Intent 2 & 3: Unified — grouped results
        $lexicons = $result['lexicons'];
        $verses = $result['verses'];
        $total = $result['total'];
        $mode = $q !== '' ? (
            preg_match('/^[HG]\d+$/i', $q) ? 'strong'
            : (preg_match('/[\x{0600}-\x{06FF}]/u', $q) ? 'arabic' : 'transliteration')
        ) : 'empty';

        // Verses now are Verse models with text_ar/text_clean — highlighting handled directly in Blade via ArabicScriptService
        // No transform needed; keep paginator as is for view to render full verse text with highlight

        $seo = $this->seoMeta->forSearch($q, $total);
        $breadcrumbs = [
            ['name' => 'الرئيسية', 'url' => url('/')],
            ['name' => 'البحث', 'url' => url('/search')],
            ['name' => "“{$q}”", 'url' => $seo['canonical']],
        ];

        // Legacy variables for view bc (results, total) + grouped (verses, lexicons)
        return view('search.results', [
            'q' => $q,
            'rawQ' => $rawQ,
            'mode' => $mode,
            'results' => $verses,
            'verses' => $verses,
            'lexicons' => $lexicons,
            'seo' => $seo,
            'breadcrumbs' => $breadcrumbs,
            'total' => $total,
            'lexicon' => $lexicons->first() ?? null, // bc for old view strong header
        ]);
    }
}
