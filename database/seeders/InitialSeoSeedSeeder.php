<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Lexicon;
use App\Models\Verse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * InitialSeoSeedSeeder — Month 1 pilot of ~40 high-value pages
 *
 * Activates is_indexed=true for:
 *  - Homepage (handled as always-indexable via service string '/')
 *  - Book: Gospel of John (Jhn)
 *  - Chapters: Jhn.1 and Gen.1 (all verses in each chapter)
 *  - Top 20 theological Strong entries (H7225, H1254, H430, G3056, G2424, G5547, G26, H8414 + 12 more)
 *
 * Total ~40: 1 homepage (implicit) + 1 book + 51+31 verses? We limit to chapters (2 chapter URLs) + 20 lexicons = 23-24 core,
 * but counting each verse as separate indexed URL would exceed 40 (31+18). So we index at chapter granularity for verses:
 * We mark verses in those chapters as indexed, but sitemap-verses will be capped to ~20 verses for Month 1? Spec says ~40 high-value pages
 * includes book + 2 chapters + 20 lexicons + homepage + static pages (About, Study Guide) = ~24-26.
 * To hit ~40, we also index 15 representative verses from those chapters (first 10 of Gen.1 + first 5 of Jhn.1) + the book.
 * Simpler: we index ALL verses in Gen.1 (31) and Jhn.1 (51) would be 82 >40, so we limit to first 10 of each for sitemap.
 * Here we seed as per spec: Chapters Jhn.1 and Gen.1 verses -> is_indexed=true for all verses in those chapters, but sitemap will be throttled to ~40 via limit.
 * For lexicons: 20 entries.
 */
class InitialSeoSeedSeeder extends Seeder
{
    /**
     * Top 20 Seed Theological Words — high-value lexicon pilot
     */
    private const SEED_STRONGS = [
        'H7225', // Bereshit — في البدء
        'H1254', // Bara — خلق
        'H430',  // Elohim — الله
        'H3068', // YHWH — يهوه
        'H7307', // Ruach — روح
        'H571',  // Emet — حق
        'H2617', // Chesed — رحمة
        'H7965', // Shalom — سلام
        'H8414', // Tohu — خَرِبَة / باطل
        'H776',  // Erets — أرض
        'H8064', // Shamayim — سماوات
        'H853',  // Et — علامة المفعول
        'G3056', // Logos — الكلمة
        'G2424', // Iesous — يسوع
        'G5547', // Christos — المسيح
        'G26',   // Agape — محبة
        'G40',   // Hagios — قدوس
        'G4102', // Pistis — إيمان
        'G4990', // Soter — مخلص
        'G4151', // Pneuma — روح
    ];

    public function run(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        // 1. Reset all to false (ensure deterministic Month 1 state)
        DB::table('books')->update(['is_indexed' => false]);
        DB::table('verses')->update(['is_indexed' => false]);
        DB::table('lexicons')->update(['is_indexed' => false]);

        // 2. Book: Gospel of John (Jhn)
        $jhn = Book::where('code', 'Jhn')->first();
        if ($jhn) {
            $jhn->update(['is_indexed' => true]);
            $this->command?->info("Indexed book: Jhn ({$jhn->name_ar})");
        }

        // 3. Chapters: Jhn.1 and Gen.1 — mark all verses in those chapters as indexed
        $gen = Book::where('code', 'Gen')->first();
        $chaptersToIndex = [];
        if ($gen) {
            $chaptersToIndex[] = [$gen->id, 1];
        }
        if ($jhn) {
            $chaptersToIndex[] = [$jhn->id, 1];
        }

        $verseCount = 0;
        foreach ($chaptersToIndex as [$bookId, $chapter]) {
            $updated = Verse::where('book_id', $bookId)->where('chapter', $chapter)->update(['is_indexed' => true]);
            $verseCount += $updated;
            $this->command?->info("Indexed chapter: book_id {$bookId} ch {$chapter} -> {$updated} verses");
        }

        // 4. Lexicons: Top 20 theological
        $lexSeeded = 0;
        foreach (self::SEED_STRONGS as $strong) {
            $lex = Lexicon::where('strong_id', $strong)->first();
            if ($lex) {
                $lex->update(['is_indexed' => true]);
                $lexSeeded++;
            } else {
                $this->command?->warn("Lexicon {$strong} not found — skipped");
            }
        }
        $this->command?->info("Indexed lexicons: {$lexSeeded}/20");

        // Summary
        $booksIndexed = Book::where('is_indexed', true)->count();
        $versesIndexed = Verse::where('is_indexed', true)->count();
        $lexIndexed = Lexicon::where('is_indexed', true)->count();
        $total = $booksIndexed + $versesIndexed + $lexIndexed + 1; // +1 homepage

        DB::statement('PRAGMA foreign_keys = ON');

        $this->command?->info("=== Initial SEO Seed (Month 1 ~40 pilot) ===");
        $this->command?->info("Books indexed: {$booksIndexed} (Jhn)");
        $this->command?->info("Verses indexed: {$versesIndexed} (Gen.1 + Jhn.1)");
        $this->command?->info("Lexicons indexed: {$lexIndexed} (20 seeds)");
        $this->command?->info("Total pilot URLs (incl. homepage): {$total} (sitemap will cap at ~40)");
        $this->command?->info("Other records remain is_indexed=false");
    }
}
