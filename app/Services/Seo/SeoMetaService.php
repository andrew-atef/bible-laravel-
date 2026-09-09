<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Book;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * SeoMetaService — dynamic SEO / OpenGraph / JSON-LD for 3-tier GEO architecture
 *
 * Responsibilities:
 *  - Canonical URLs (absolute, trailing-slash normalized)
 *  - Arabic-first meta titles/descriptions tailored for search intent
 *  - OpenGraph (Facebook/WhatsApp rich previews)
 *  - Schema.org JSON-LD (BreadcrumbList, Article/ScholarlyArticle, DefinedTerm)
 */
class SeoMetaService
{
    private string $appName;
    private string $appUrl;

    public function __construct()
    {
        $this->appName = (string) config('app.name', 'المنصة الكتابية');
        $this->appUrl = rtrim((string) config('app.url', url('/')), '/');
    }

    /**
     * Chapter SEO — /read/{book}/{chapter}
     *
     * @return array{
     *   title:string,
     *   description:string,
     *   canonical:string,
     *   og:array,
     *   jsonLd:array,
     *   breadcrumbs:array
     * }
     */
    public function forChapter(Book $book, int $chapter, int $versesCount = 0): array
    {
        $title = sprintf('%s %d - قراءة عربية مع الأصل العبري واليوناني | %s', $book->name_ar, $chapter, $this->appName);
        $description = sprintf(
            'اقرأ %s الإصحاح %d بالعربية (ترجمة الفانديك) مع النص العبري/اليوناني كلمة بكلمة، النطق، التحليل النحوي، وتفسير الآباء. %d آية مُحللة من 382k كلمة.',
            $book->name_ar,
            $chapter,
            $versesCount
        );

        $canonical = URL::to("/read/{$book->slug}/{$chapter}");

        $og = [
            'title' => $title,
            'description' => Str::limit($description, 160),
            'url' => $canonical,
            'type' => 'article',
            'locale' => 'ar_AR',
            'site_name' => $this->appName,
            'image' => URL::to('/images/og-chapter.jpg'),
        ];

        $breadcrumbs = [
            ['name' => 'الرئيسية', 'url' => URL::to('/')],
            ['name' => $book->name_ar, 'url' => URL::to("/read/{$book->slug}/1")],
            ['name' => "الإصحاح {$chapter}", 'url' => $canonical],
        ];

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                fn ($i, $crumb) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $crumb['name'],
                    'item' => $crumb['url'],
                ],
                array_keys($breadcrumbs),
                $breadcrumbs
            ),
        ];

        // Also Chapter as Article
        $jsonLdArticle = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $title,
            'description' => $description,
            'inLanguage' => 'ar',
            'isPartOf' => [
                '@type' => 'Book',
                'name' => $book->name_ar,
                'alternateName' => $book->name_en,
            ],
            'mainEntityOfPage' => $canonical,
        ];

        return compact('title', 'description', 'canonical', 'og', 'jsonLd', 'breadcrumbs') + ['jsonLdArticle' => $jsonLdArticle];
    }

    /**
     * Verse Study SEO — /verse/{osis}
     *
     * @param array $study Result from BibleReaderService::getVerseStudy
     * @return array{title:string,description:string,canonical:string,og:array,jsonLd:array,breadcrumbs:array}
     */
    public function forVerse(array $study): array
    {
        $verse = $study['verse'] ?? null;
        $book = $study['book'] ?? null;
        $tokens = $study['tokens'] ?? null;

        $osis = $verse['osis_id'] ?? $study['verse']['osis_id'] ?? 'Gen.1.1';
        [$code, $ch, $v] = array_pad(explode('.', $osis), 3, '1');
        $bookAr = $book['name_ar'] ?? 'التكوين';
        $bookEn = $book['name_en'] ?? 'Genesis';

        // Build Arabic context sentence for description (first 3 arabic words)
        $arabicSnippet = '';
        if ($tokens && method_exists($tokens, 'pluck')) {
            $arabicSnippet = $tokens->pluck('arabic_word')->take(8)->implode(' ');
        } elseif (isset($study['tokens']) && is_array($study['tokens'])) {
            $arabicSnippet = implode(' ', array_slice(array_column($study['tokens'], 'arabic_word'), 0, 8));
        }

        $title = sprintf('%s %s:%s - دراسة متعمقة بالأصل العبري/اليوناني | %s', $bookAr, $ch, $v, $this->appName);
        $description = sprintf(
            '%s %s:%s بالأصل العبري وترجمة الفانديك مع تفسير الآباء. النص: %s. دراسة كلمة بكلمة مع سترونغ، النطق، والتحليل النحوي.',
            $bookAr,
            $ch,
            $v,
            Str::limit($arabicSnippet, 80, '…')
        );

        $canonical = URL::to("/verse/{$osis}");

        $og = [
            'title' => $title,
            'description' => Str::limit($description, 170),
            'url' => $canonical,
            'type' => 'article',
            'locale' => 'ar_AR',
            'site_name' => $this->appName,
            'image' => URL::to('/images/og-verse.jpg'),
        ];

        $bookSlug = $book['slug'] ?? strtolower($code);
        $breadcrumbs = [
            ['name' => 'الرئيسية', 'url' => URL::to('/')],
            ['name' => $bookAr, 'url' => URL::to("/read/{$bookSlug}/{$ch}")],
            ['name' => "الإصحاح {$ch}", 'url' => URL::to("/read/{$bookSlug}/{$ch}")],
            ['name' => "آية {$v}", 'url' => $canonical],
        ];

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'ScholarlyArticle',
            'headline' => $title,
            'description' => $description,
            'inLanguage' => 'ar',
            'isPartOf' => [
                '@type' => 'Book',
                'name' => $bookAr,
                'alternateName' => $bookEn,
            ],
            'citation' => $osis,
            'about' => $bookAr . " {$ch}:{$v}",
            'mainEntityOfPage' => $canonical,
            'author' => [
                '@type' => 'Organization',
                'name' => $this->appName,
            ],
        ];

        $breadcrumbLd = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                fn ($i, $crumb) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $crumb['name'],
                    'item' => $crumb['url'],
                ],
                array_keys($breadcrumbs),
                $breadcrumbs
            ),
        ];

        return compact('title', 'description', 'canonical', 'og', 'jsonLd', 'breadcrumbs') + ['breadcrumbLd' => $breadcrumbLd];
    }

    /**
     * Lexicon SEO — /lexicon/{strong_id}
     *
     * @param array $summary Result from LexiconStudyService::getLexiconSummary
     * @return array{title:string,description:string,canonical:string,og:array,jsonLd:array,breadcrumbs:array}
     */
    public function forLexicon(array $summary): array
    {
        $lex = $summary['lexicon'];
        $strong = $lex['strong_id'];
        $lemma = $lex['lemma'];
        $trans = $lex['transliteration'] ?? '';
        $lang = $lex['language'] === 'hebrew' ? 'العبري' : ($lex['language'] === 'greek' ? 'اليوناني' : 'الآرامي');
        $def = Str::limit(strip_tags($lex['strongs_definition'] ?? ''), 120, '…');
        $occ = $summary['occurrences_count'];

        $title = sprintf('%s (%s) - %s | معجم سترونغ %s | %s', $lemma, $strong, $trans, $lang, $this->appName);
        $description = sprintf(
            'معنى %s (%s) في %s: %s وردت %d مرة في الكتاب المقدس. الترجمات العربية: %s. دراسة معجمية شاملة.',
            $lemma,
            $strong,
            $lang,
            $def,
            $occ,
            implode('، ', array_slice(array_column($summary['top_arabic_translations'], 'arabic_word'), 0, 3))
        );

        $canonical = URL::to("/lexicon/{$strong}");

        $og = [
            'title' => $title,
            'description' => Str::limit($description, 170),
            'url' => $canonical,
            'type' => 'article',
            'locale' => 'ar_AR',
            'site_name' => $this->appName,
            'image' => URL::to('/images/og-lexicon.jpg'),
        ];

        $breadcrumbs = [
            ['name' => 'الرئيسية', 'url' => URL::to('/')],
            ['name' => 'المعجم', 'url' => URL::to('/lexicon/H7225')],
            ['name' => "{$lemma} {$strong}", 'url' => $canonical],
        ];

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'DefinedTerm',
            'termCode' => $strong,
            'name' => $lemma . " ({$trans})",
            'alternateName' => $trans,
            'description' => $lex['strongs_definition'] ?? '',
            'inDefinedTermSet' => [
                '@type' => 'DefinedTermSet',
                'name' => $lang === 'العبري' ? 'معجم سترونغ العبري' : 'معجم سترونغ اليوناني',
                'url' => $canonical,
            ],
            'url' => $canonical,
        ];

        $breadcrumbLd = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                fn ($i, $crumb) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $crumb['name'],
                    'item' => $crumb['url'],
                ],
                array_keys($breadcrumbs),
                $breadcrumbs
            ),
        ];

        return compact('title', 'description', 'canonical', 'og', 'jsonLd', 'breadcrumbs') + ['breadcrumbLd' => $breadcrumbLd];
    }

    /**
     * Search SEO — /search?q=...
     *
     * @return array{title:string,description:string,canonical:string,og:array,jsonLd:array}
     */
    public function forSearch(string $query, int $total = 0): array
    {
        $q = trim($query);
        $title = $q !== ''
            ? sprintf('نتائج البحث عن "%s" | %s', Str::limit($q, 30), $this->appName)
            : sprintf('البحث في الكتاب المقدس | %s', $this->appName);

        $description = $q !== ''
            ? sprintf('بحث عن "%s" في النص العربي (مع/بدون تشكيل)، أرقام سترونغ، والنطق. %d نتيجة.', $q, $total)
            : 'ابحث بالنص العربي مع أو بدون تشكيل، برقم سترونغ (H7225)، أو بالنطق (Logos, Tohu).';

        $canonical = $q !== '' ? URL::to('/search') . '?q=' . urlencode($q) : URL::to('/search');

        $og = [
            'title' => $title,
            'description' => Str::limit($description, 160),
            'url' => $canonical,
            'type' => 'website',
            'locale' => 'ar_AR',
            'site_name' => $this->appName,
        ];

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'SearchResultsPage',
            'name' => $title,
            'description' => $description,
            'url' => $canonical,
        ];

        return compact('title', 'description', 'canonical', 'og', 'jsonLd');
    }
}
