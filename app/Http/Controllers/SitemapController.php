<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Lexicon;
use App\Models\Verse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

/**
 * SitemapController — Dynamic Segmented Sitemap (Staged Indexation)
 *
 * Month 1: ~40 URLs (homepage + 1 book + 51+31 verses capped + 20 lexicons)
 * Humans browse everything; bots only see is_indexed=true via sitemap + robots meta.
 */
class SitemapController extends Controller
{
    /**
     * Sitemap index — lists all segmented sitemaps
     * Route: /sitemap.xml
     */
    public function index(): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= $this->sitemapEntry(url('/sitemap-core.xml'));
        $xml .= $this->sitemapEntry(url('/sitemap-verses.xml'));
        $xml .= $this->sitemapEntry(url('/sitemap-lexicon.xml'));
        $xml .= '</sitemapindex>';

        return response($xml, 200)->header('Content-Type', 'text/xml; charset=utf-8');
    }

    /**
     * Core sitemap — static + indexed books only
     * Route: /sitemap-core.xml
     */
    public function core(): Response
    {
        $urls = [];

        // Homepage (always indexed)
        $urls[] = $this->urlEntry(url('/'), now()->toDateString(), 'daily', '1.0');

        // Static core pages (if they exist — always include for pilot)
        $urls[] = $this->urlEntry(url('/about'), now()->toDateString(), 'monthly', '0.6');
        $urls[] = $this->urlEntry(url('/study-guide'), now()->toDateString(), 'monthly', '0.6');

        // Only books where is_indexed=true (Month 1: Jhn only) — strict schema: id is canonical 1..66
        $books = Book::where('is_indexed', true)
            ->select(['slug', 'code'])
            ->orderBy('id')
            ->get();

        foreach ($books as $book) {
            // Book landing -> redirect to read/{book}/1
            $urls[] = $this->urlEntry(url("/read/{$book->slug}/1"), now()->toDateString(), 'weekly', '0.8');
        }

        $xml = $this->wrapUrlset($urls);

        return response($xml, 200)->header('Content-Type', 'text/xml; charset=utf-8');
    }

    /**
     * Verses/Chapters sitemap — ONLY where is_indexed=true
     * Route: /sitemap-verses.xml
     * Month 1: Gen.1 (31 verses) + Jhn.1 (51 verses) but capped to ~40 total pilot,
     * so we limit to first 40 verses ordered canonically (or homepage + book already counted).
     * Spec says Ensure Month 1 does not exceed ~40 seed URLs — so verse sitemap is capped.
     */
    public function verses(): Response
    {
        // Only indexed verses — Month 1 pilot has 82 (Gen.1 31 + Jhn.1 51) but we cap sitemap to ~20 to keep total ~40
        // To ensure both pilot chapters appear, we take 10 from each book (Gen first, Jhn second)
        $urls = [];

        // Chapter URLs for indexed chapters (Gen.1, Jhn.1) — 2 chapters, always include — strict schema: books.id
        $chapters = Verse::query()
            ->join('books', 'books.id', '=', 'verses.book_id')
            ->where('verses.is_indexed', true)
            ->selectRaw('books.slug, verses.chapter, MAX(verses.updated_at) as updated_at, books.id')
            ->groupBy('books.slug', 'verses.chapter', 'books.id')
            ->orderBy('books.id')
            ->orderBy('verses.chapter')
            ->limit(5)
            ->get();

        foreach ($chapters as $ch) {
            $urls[] = $this->urlEntry(url("/read/{$ch->slug}/{$ch->chapter}"), $ch->updated_at?->toDateString() ?? now()->toDateString(), 'weekly', '0.8');
        }

        // Verses: take 7 per book to ensure both Gen and Jhn appear balanced — strict schema: books.id
        $booksWithVerses = Verse::query()
            ->join('books', 'books.id', '=', 'verses.book_id')
            ->where('verses.is_indexed', true)
            ->selectRaw('books.code, books.id')
            ->groupBy('books.code', 'books.id')
            ->orderBy('books.id')
            ->get();

        $verseUrls = collect();
        foreach ($booksWithVerses as $b) {
            $verses = Verse::query()
                ->join('books', 'books.id', '=', 'verses.book_id')
                ->where('verses.is_indexed', true)
                ->where('books.code', $b->code)
                ->select(['verses.osis_id', 'verses.updated_at'])
                ->orderBy('verses.chapter')
                ->orderBy('verses.verse')
                ->limit(7)
                ->get();

            foreach ($verses as $v) {
                $verseUrls->push($this->urlEntry(url("/verse/{$v->osis_id}"), $v->updated_at?->toDateString() ?? now()->toDateString(), 'weekly', '0.9'));
            }
        }

        // If still less than 20, fill with next verses ordered canonically (already have 20 if 2 books *10)
        $urls = array_merge($urls, $verseUrls->toArray());

        // Deduplicate and cap to 16 to keep total sitemap ~40 (core 4 + verses 16 + lex 20 = 40)
        $urls = collect($urls)->unique()->values()->toArray();
        $urls = array_slice($urls, 0, 16);

        $xml = $this->wrapUrlset($urls);

        return response($xml, 200)->header('Content-Type', 'text/xml; charset=utf-8');
    }

    /**
     * Lexicon sitemap — ONLY where is_indexed=true
     * Route: /sitemap-lexicon.xml
     */
    public function lexicon(): Response
    {
        $lexicons = Lexicon::where('is_indexed', true)
            ->select(['strong_id', 'updated_at'])
            ->orderBy('strong_id')
            ->limit(30) // 20 pilot + buffer
            ->get();

        $urls = [];
        foreach ($lexicons as $lex) {
            $urls[] = $this->urlEntry(url("/lexicon/{$lex->strong_id}"), $lex->updated_at?->toDateString() ?? now()->toDateString(), 'weekly', '0.7');
        }

        // Ensure cap to 20
        $urls = array_slice($urls, 0, 20);

        $xml = $this->wrapUrlset($urls);

        return response($xml, 200)->header('Content-Type', 'text/xml; charset=utf-8');
    }

    private function sitemapEntry(string $url): string
    {
        return "  <sitemap><loc>{$this->escape($url)}</loc><lastmod>" . now()->toDateString() . "</lastmod></sitemap>\n";
    }

    private function urlEntry(string $loc, string $lastmod, string $changefreq = 'weekly', string $priority = '0.5'): string
    {
        return "  <url><loc>{$this->escape($loc)}</loc><lastmod>{$lastmod}</lastmod><changefreq>{$changefreq}</changefreq><priority>{$priority}</priority></url>\n";
    }

    private function wrapUrlset(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= $u;
        }
        $xml .= '</urlset>';

        return $xml;
    }

    private function escape(string $url): string
    {
        return htmlspecialchars($url, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
