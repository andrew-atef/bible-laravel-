<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\TranslateLexiconJob;
use App\Models\ArabicConcordance;
use App\Models\Lexicon;
use App\Models\Verse;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TranslateLexiconCommand extends Command
{
    protected $signature = 'bible:translate-lexicon 
                            {--verse= : Translate words in a verse or range (e.g. Gen.1.1 or Gen.1.1-Gen.1.5)}
                            {--strong= : Translate a specific Strong ID (e.g. H7225, H410)}
                            {--limit=50 : Maximum number of words to dispatch}
                            {--force : Re-translate even if already translated}
                            {--sync : Execute synchronously without queue}';

    protected $description = 'Translate English Strong lexicon entries to scholarly Arabic using Gemini API';

    public function handle(): int
    {
        $strongOpt = $this->option('strong');
        $verseOpt = $this->option('verse');
        $force = (bool) $this->option('force');
        $sync = (bool) $this->option('sync');
        $limit = (int) $this->option('limit');

        $strongIds = collect();

        // 1. تحديد أرقام سترونغ حسب الخيارات
        if ($strongOpt) {
            $strongIds->push(strtoupper(trim((string) $strongOpt)));
        } elseif ($verseOpt) {
            $strongIds = $this->resolveStrongsFromVerseRange((string) $verseOpt);
        } else {
            // جلب الكلمات التي لم تُترجم بعد
            $query = Lexicon::query();
            if (!$force) {
                $query->whereNull('translated_at');
            }
            $strongIds = $query->limit($limit)->pluck('strong_id');
        }

        $strongIds = $strongIds->unique()->filter();

        if ($strongIds->isEmpty()) {
            $this->warn('No Strong IDs found matching the criteria.');
            return self::SUCCESS;
        }

        $this->info("Found {$strongIds->count()} Strong ID(s) to process.");

        $bar = $this->output->createProgressBar($strongIds->count());
        $bar->start();

        foreach ($strongIds as $strongId) {
            if ($sync) {
                app(TranslateLexiconJob::class, ['strongId' => $strongId])->handle(
                    app(\App\Services\Biblical\GeminiLexiconTranslationService::class)
                );
            } else {
                TranslateLexiconJob::dispatch($strongId);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($sync) {
            $this->info('✅ All selected words translated synchronously.');
        } else {
            $this->info('🚀 Jobs dispatched to queue successfully. Run "php artisan queue:work" to process them.');
        }

        return self::SUCCESS;
    }

    /**
     * استخراج كل أرقام سترونغ الواردة في آية أو نطاق آيات (مثل Gen.1.1 أو Gen.1.1-Gen.1.5)
     */
    private function resolveStrongsFromVerseRange(string $range): Collection
    {
        $range = trim($range);

        if (str_contains($range, '-')) {
            [$start, $end] = explode('-', $range, 2);
            $start = trim($start);
            $end = trim($end);

            $this->info("Resolving verses between {$start} and {$end}...");

            // جلب أرقام سترونغ الواقعة في هذا النطاق الكنسي
            // Use canonical ordering via book_number if available, fallback to string compare
            $verseOsisList = Verse::query()
                ->where('osis_id', '>=', $start)
                ->where('osis_id', '<=', $end)
                ->pluck('osis_id');

            // If string compare fails for canonical (e.g., Gen.10 vs Gen.2), fallback to book_number ordering
            if ($verseOsisList->isEmpty()) {
                $startParts = explode('.', $start);
                $endParts = explode('.', $end);
                if (count($startParts) === 3 && count($endParts) === 3) {
                    $verseOsisList = Verse::join('books', 'books.id', '=', 'verses.book_id')
                        ->where('books.code', $startParts[0])
                        ->whereBetween('verses.chapter', [(int)$startParts[1], (int)$endParts[1]])
                        ->pluck('verses.osis_id');
                }
            }

            return ArabicConcordance::query()
                ->whereIn('verse_osis', $verseOsisList)
                ->distinct()
                ->pluck('strong_id');
        }

        // آية واحدة
        return ArabicConcordance::query()
            ->where('verse_osis', $range)
            ->distinct()
            ->pluck('strong_id');
    }
}
