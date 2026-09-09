<?php

declare(strict_types=1);

use App\Http\Controllers\LexiconController;
use App\Http\Controllers\ReaderController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\VerseStudyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 3-Tier SEO Architecture (GEO — Generative Engine Optimization)
|--------------------------------------------------------------------------
| Tier 1 — /read/{book}/{chapter}  — Primary clean reading (chapter spine)
| Tier 2 — /verse/{osis}           — Deep study (Gen.1.1, 1Sa.15.21)
| Tier 3 — /lexicon/{strong_id}   — Lexical hub (H7225, G2424)
| Tier X — /search                 — Full-text discovery (Arabic/Tashkeel/Translit/Strong)
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    $seo = [
        'title' => 'المنصة الكتابية العربية — دراسة كلمة بكلمة مع الأصل العبري واليوناني | BibleHub عربي',
        'description' => 'اقرأ الكتاب المقدس بالعربية الفانديك مع النص العبري واليوناني كلمة بكلمة، النطق، والتحليل النحوي. 382,535 كلمة مُحللة.',
        'canonical' => url('/'),
        'og' => [
            'title' => 'المنصة الكتابية العربية — بديل BibleHub الحديث',
            'description' => 'دراسة كتابية عربية متعمقة: 66 سفر، 31,102 آية، 14,298 كلمة معجمية مع النطق والشواهد.',
            'url' => url('/'),
            'type' => 'website',
            'locale' => 'ar_AR',
            'site_name' => config('app.name','المنصة الكتابية'),
            'image' => url('/images/og-home.jpg'),
        ],
        'jsonLd' => [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('app.name','المنصة الكتابية'),
            'url' => url('/'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => url('/search').'?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ],
        'currentEntity' => '/',
        'breadcrumbs' => [['name'=>'الرئيسية','url'=>url('/')]],
    ];
    return view('welcome', ['currentEntity' => '/', 'seo' => $seo]);
})->name('home');

// Tier 1: Clean reading — e.g. /read/al-takwin/1, /read/mat/5
Route::get('/read/{book}/{chapter}', [ReaderController::class, 'showChapter'])
    ->where(['book' => '[a-z0-9\-]+', 'chapter' => '[0-9]+'])
    ->name('reader.chapter');

// Tier 2: Deep verse study — e.g. /verse/Gen.1.1 (dots preserved)
Route::get('/verse/{osis}', [VerseStudyController::class, 'show'])
    ->where('osis', '[1-3]?[A-Za-z]{2,3}\.[0-9]+\.[0-9]+')
    ->name('verse.study');

// Tier 3: Lexical hub — BibleHub-style — e.g. /lexicon/H7225, /lexicon/G2424
Route::get('/lexicon/{strong_id}', [LexiconController::class, 'show'])
    ->where('strong_id', '[HGhg][0-9]+')
    ->name('lexicon.show');

// Search — ?q= (Arabic with/without Tashkeel, Strong ID, Transliteration)
Route::get('/search', [SearchController::class, 'query'])
    ->name('search.query');

// Staged Indexation — Sitemap Gatekeeper (Month 1 ~40 URLs)
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-core.xml', [SitemapController::class, 'core'])->name('sitemap.core');
Route::get('/sitemap-verses.xml', [SitemapController::class, 'verses'])->name('sitemap.verses');
Route::get('/sitemap-lexicon.xml', [SitemapController::class, 'lexicon'])->name('sitemap.lexicon');

// Admin Panel — Manual Indexation Controls (Arabic, sitemap cache flush on toggle)
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\BookAdminController as AdminBooks;
use App\Http\Controllers\Admin\VerseAdminController as AdminVerses;
use App\Http\Controllers\Admin\LexiconAdminController as AdminLexicon;
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboard::class, 'index'])->name('dashboard');
    // Books
    Route::get('/books', [AdminBooks::class, 'index'])->name('books.index');
    Route::patch('/books/{book}/toggle', [AdminBooks::class, 'toggle'])->name('books.toggle');
    Route::patch('/books/{book}/enable', [AdminBooks::class, 'enableBook'])->name('books.enableBook');
    Route::patch('/books/{book}/disable', [AdminBooks::class, 'disableBook'])->name('books.disableBook');
    Route::post('/books/bulk-enable', [AdminBooks::class, 'bulkEnable'])->name('books.bulkEnable');
    Route::post('/books/bulk-disable', [AdminBooks::class, 'bulkDisable'])->name('books.bulkDisable');
    // Verses
    Route::get('/verses', [AdminVerses::class, 'index'])->name('verses.index');
    Route::patch('/verses/{verse}/toggle', [AdminVerses::class, 'toggle'])->name('verses.toggle');
    Route::post('/verses/bulk-enable', [AdminVerses::class, 'bulkEnable'])->name('verses.bulkEnable');
    Route::post('/verses/bulk-disable', [AdminVerses::class, 'bulkDisable'])->name('verses.bulkDisable');
    // Lexicon
    Route::get('/lexicon', [AdminLexicon::class, 'index'])->name('lexicon.index');
    Route::patch('/lexicon/{lexicon}/toggle', [AdminLexicon::class, 'toggle'])->name('lexicon.toggle');
    Route::post('/lexicon/bulk-enable', [AdminLexicon::class, 'bulkEnable'])->name('lexicon.bulkEnable');
    Route::post('/lexicon/bulk-disable', [AdminLexicon::class, 'bulkDisable'])->name('lexicon.bulkDisable');
});
