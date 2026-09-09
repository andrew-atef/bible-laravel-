<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Book;
use App\Services\Biblical\BibleReaderService;
use App\Services\Seo\SeoMetaService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * ReaderController — /read/{book}/{chapter}
 *
 * Primary clean reading page with progressive disclosure drawer data.
 */
class ReaderController extends Controller
{
    public function __construct(
        private readonly BibleReaderService $bibleReader,
        private readonly SeoMetaService $seoMeta
    ) {}

    /**
     * Show chapter reading page.
     *
     * @throws NotFoundHttpException
     */
    public function showChapter(Request $request, string $book, int $chapter): View
    {
        // Strict validation — chapter must be 1..150 (Psalms max)
        if ($chapter < 1 || $chapter > 150) {
            abort(404, 'الإصحاح غير موجود');
        }

        // Slug sanitization (Arabic-first but URL is latin slug) — support genesis alias via Book::findByIdentifier
        $slug = strtolower(trim($book));
        $bookModel = Book::findByIdentifier($slug);

        // Service handles 404 gracefully but we also want explicit 404 for invalid slug
        $serviceSlug = $bookModel?->slug ?? $slug;
        $data = $this->bibleReader->getChapter($serviceSlug, $chapter);

        // If service returned empty but bookModel exists, try to recover bookModel from data
        if ($bookModel === null && ! empty($data['book'])) {
            $bookModel = Book::find($data['book']['id'] ?? null);
        }

        if ($bookModel === null || $data['verses_count'] === 0) {
            // Distinguish unknown book vs empty chapter
            if ($bookModel === null && ! Book::findByIdentifier($slug)) {
                abort(404, "السفر '{$book}' غير موجود");
            }
            // Book exists but chapter has no verses -> 404 chapter
            abort(404, "الإصحاح {$chapter} غير موجود في {$bookModel?->name_ar}");
        }

        // SEO — dynamic canonical, title, OG, BreadcrumbList JSON-LD
        $seo = $this->seoMeta->forChapter($bookModel, $chapter, $data['verses_count']);

        // Progressive disclosure: enrich each token with drawer data attributes
        // We do not mutate DB; we prepare view-friendly arrays
        $verses = $data['verses']; // Collection<Verse> with tokens + tokens.lexicon

        // Navigation already resolved by service (cross-book)
        $nextChapter = $data['next_chapter'];
        $prevChapter = $data['prev_chapter'];

        // Breadcrumbs for view (also in SEO)
        $breadcrumbs = $seo['breadcrumbs'];

        // Gatekeeper: chapter indexability — verse with is_indexed=true OR book is_indexed
        $currentEntity = $verses->firstWhere('is_indexed', true) ?? $bookModel;

        // Preferred modern view: bible.reader (YouVersion/Apple Books), fallback to legacy read.chapter for backward compat
        if (view()->exists('bible.reader')) {
            return view('bible.reader', compact('bookModel', 'verses', 'chapter', 'nextChapter', 'prevChapter', 'seo', 'breadcrumbs', 'currentEntity') + [
                'commentaries' => $data['commentaries'],
            ]);
        }

        return view('read.chapter', compact('bookModel', 'verses', 'chapter', 'nextChapter', 'prevChapter', 'seo', 'breadcrumbs', 'currentEntity') + [
            'commentaries' => $data['commentaries'],
        ]);
    }
}
