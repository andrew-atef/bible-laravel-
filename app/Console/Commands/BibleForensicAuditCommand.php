<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ArabicConcordance;
use App\Models\Lexicon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class BibleForensicAuditCommand extends Command
{
    protected $signature = 'bible:forensic-audit
                            {--fix : Automatically repair dirty glosses and broken derivation links in-place}';

    protected $description = 'Forensic audit of live data flaws (H1255 derivation, Gen.1.1 glosses) with optional in-place remediation';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');

        $this->newLine();
        $this->line('  <bg=red;fg=white>  FORENSIC AUDIT  </>  <fg=cyan>Biblical database — live data flaws</>');
        $this->line('  Mode: '.($fix ? '<bg=yellow;fg=black> --fix  IN-PLACE REMEDIATION </>' : '<fg=green>read-only inspection</> (pass --fix to repair)'));
        $this->newLine();

        // ─── PHASE 1 ─────────────────────────────────────────────
        $this->components->info('PHASE 1 — Forensic Inspection & Environment Diagnostics');

        $dbPath = $this->diagnoseEnvironment();
        $beforeH1255 = $this->inspectH1255();
        $beforeGen11 = $this->inspectGen11Word1();
        $scan = $this->globalCleanlinessScan();
        $this->inspectSummaryCache(false);

        if (! $fix) {
            $this->newLine();
            $this->components->warn('Audit complete (read-only). Re-run with --fix to repair in-place.');
            $this->line('  <fg=gray>php artisan bible:forensic-audit --fix</>');

            return self::SUCCESS;
        }

        // ─── PHASE 2 ─────────────────────────────────────────────
        $this->newLine();
        $this->components->info('PHASE 2 — In-Place Remediation (transactional)');

        try {
            $report = DB::transaction(function () {
                return [
                    'gloss' => $this->fixGlosses(),
                    'deriv' => $this->fixDerivations(),
                ];
            });

            $this->clearFrameworkCache();
            $this->printFixSummary($report);

            // Close the loop: prove the stale payload is actually gone.
            $this->newLine();
            $this->line('  <fg=yellow>Post-fix cache proof</>');
            $this->inspectSummaryCache(true);
        } catch (Throwable $e) {
            $this->components->error('Remediation FAILED — transaction rolled back: '.$e->getMessage());

            return self::FAILURE;
        }

        // ─── Before / After verification ─────────────────────────
        $this->newLine();
        $this->components->info('VERIFICATION — Before / After');
        $this->verifyH1255($beforeH1255);
        $this->verifyGen11($beforeGen11);

        $this->newLine();
        $this->components->info('Done. Active DB: '.$dbPath);

        return self::SUCCESS;
    }

    // ═══════════════════ PHASE 1 ═══════════════════

    private function diagnoseEnvironment(): string
    {
        $this->line('  <fg=yellow>1) Database file diagnostics</>');

        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $configured = (string) config("database.connections.{$connection}.database");
        // config() stores a relative path — resolve to an absolute one for the report.
        $absolute = realpath($configured) ?: base_path($configured);
        $dbPath = $absolute;

        $this->components->twoColumnDetail('Active connection', "<fg=green>{$connection}</>");
        $this->components->twoColumnDetail('Driver', "<fg=green>{$driver}</>");
        $this->components->twoColumnDetail('Absolute path', "<fg=green>{$dbPath}</>");

        if ($dbPath !== '' && is_file($dbPath)) {
            $sizeMb = round((float) filesize($dbPath) / 1048576, 2);
            $mtime = date('Y-m-d H:i:s', (int) filemtime($dbPath));
            $this->components->twoColumnDetail('File size', "<fg=green>{$sizeMb} MB</>");
            $this->components->twoColumnDetail('Last modified', "<fg=green>{$mtime}</>");
            $this->components->twoColumnDetail('Readable', is_readable($dbPath) ? '<fg=green>yes</>' : '<fg=red>NO</>');
            $this->components->twoColumnDetail('Writable', is_writable($dbPath) ? '<fg=green>yes</>' : '<fg=red>NO — --fix will fail</>');
        } else {
            $this->components->warn("Database file not found at: {$dbPath} (non-file driver or missing file — size/mtime skipped).");
        }

        // Ground truth: the ACTUAL file SQLite opened (kills relative-path ambiguity).
        try {
            $pragma = DB::select('PRAGMA database_list');
            $realFile = (string) ($pragma[0]->file ?? '');
            $this->components->twoColumnDetail('PRAGMA database_list (real file)', $realFile !== '' ? "<fg=green>{$realFile}</>" : '<fg=red>empty (in-memory?)</>');
        } catch (Throwable $e) {
            $this->components->warn('PRAGMA database_list failed: '.$e->getMessage());
        }

        // Cache store identity — CLI and FPM must share it for flushes to work.
        $this->components->twoColumnDetail('Cache store', '<fg=green>'.config('cache.default').'</>');

        return $dbPath;
    }

    /** @return array<string, string|null> */
    private function inspectH1255(): array
    {
        $this->line('  <fg=yellow>2) Inspect H1255 (lexicons)</>');

        $row = Lexicon::where('strong_id', 'H1255')->first();

        if ($row === null) {
            $this->components->error('H1255 NOT FOUND in lexicons!');

            return ['word_origin' => null, 'root_strongs' => null, 'roots_json' => null];
        }

        $rootsJson = is_string($row->getAttribute('roots_json'))
            ? $row->getAttribute('roots_json')
            : json_encode($row->getAttribute('roots_json'), JSON_UNESCAPED_UNICODE);

        $this->table(
            ['Column', 'Raw value'],
            [
                ['word_origin', (string) $row->word_origin],
                ['root_strongs', (string) $row->root_strongs],
                ['roots_json', $this->truncate((string) $rootsJson, 160)],
            ]
        );

        $origin = (string) $row->word_origin;
        $hasDangling = str_contains($origin, 'variation of ;');
        $lacksH4757 = ! str_contains(strtoupper($origin), 'H4757');
        $rootsEmpty = $this->rootsJsonHasNoId($row, 'H4757');

        if ($hasDangling || $lacksH4757 || $rootsEmpty) {
            $flags = [];
            if ($hasDangling) {
                $flags[] = 'dangling "variation of ;"';
            }
            if ($lacksH4757) {
                $flags[] = 'H4757 stripped from word_origin';
            }
            if ($rootsEmpty) {
                $flags[] = 'roots_json missing H4757';
            }
            $this->components->warn('H1255 BROKEN: '.implode(' + ', $flags));
        } else {
            $this->components->info('H1255 looks healthy (H4757 linked).');
        }

        $h4757 = Lexicon::where('strong_id', 'H4757')->first();
        $this->components->twoColumnDetail(
            'H4757 exists in DB',
            $h4757 ? "<fg=green>yes</> — lemma: {$h4757->lemma}" : '<fg=red>NO</>'
        );

        return [
            'word_origin' => (string) $row->word_origin,
            'root_strongs' => (string) $row->root_strongs,
            'roots_json' => (string) $rootsJson,
        ];
    }

    /** @return array<string, string|null> */
    private function inspectGen11Word1(): array
    {
        $this->line('  <fg=yellow>3) Inspect Gen.1.1 word 1 (arabic_concordance)</>');

        $tok = ArabicConcordance::where('verse_osis', 'Gen.1.1')->where('word_pos', 1)->first();

        if ($tok === null) {
            $this->components->error('Gen.1.1 word_pos=1 NOT FOUND!');

            return ['strong_id' => null, 'gloss_en' => null, 'arabic_word' => null, 'transliteration' => null];
        }

        $this->table(
            ['Column', 'Value'],
            [
                ['strong_id', (string) $tok->strong_id],
                ['gloss_en', (string) $tok->gloss_en],
                ['arabic_word', (string) $tok->arabic_word],
                ['transliteration', (string) $tok->transliteration],
            ]
        );

        $gloss = (string) $tok->gloss_en;
        $dirty = str_contains($gloss, '{') || str_contains($gloss, '}') || str_contains($gloss, ';');

        if ($dirty) {
            $this->components->warn("gloss_en DIRTY — raw tags present: '{$gloss}' (expected 'in / beginning').");
        } else {
            $this->components->info("gloss_en clean: '{$gloss}'.");
        }

        return [
            'strong_id' => (string) $tok->strong_id,
            'gloss_en' => $gloss,
            'arabic_word' => (string) $tok->arabic_word,
            'transliteration' => (string) $tok->transliteration,
        ];
    }

    /**
     * Step 5 — inspect the cached lexicon summary payload.
     *
     * /lexicon/* pages are served from Cache key lexicon_summary_v3_{ID}
     * (TTL 7 days). If the DB is clean but this payload is dirty, the page
     * will keep showing stale data until the cache is flushed — this check
     * distinguishes "stale cache" from "stale database / wrong file".
     */
    private function inspectSummaryCache(bool $postFix = false): void
    {
        $this->line('  <fg=yellow>5) Cached summary payload (lexicon_summary_v3_H1255, TTL 7d)</>');

        $key = 'lexicon_summary_v3_H1255';
        $cached = Cache::get($key);

        if (! is_array($cached)) {
            $this->components->twoColumnDetail('Cache key', '<fg=green>MISS — page will rebuild from live DB</>');

            return;
        }

        $lex = $cached['lexicon'] ?? [];
        $this->table(
            ['Field (cached)', 'Value'],
            [
                ['word_origin', (string) ($lex['word_origin'] ?? '—')],
                ['root_ids', implode(',', (array) ($lex['root_ids'] ?? []))],
            ]
        );

        $dirty = str_contains((string) ($lex['word_origin'] ?? ''), 'variation of ;')
            || empty($lex['root_ids']);

        $this->line($dirty
            ? '  <fg=red>⚠️ STALE CACHE — payload predates the DB fix. Run --fix (flushes cache) or php artisan cache:clear.</>'
            : '  <fg=green>✅ Cache payload matches clean DB. If the page still shows dirt, suspect HTTP/CDN/browser cache — hard-refresh.</>');

        if ($dirty && $postFix) {
            $this->line('  <fg=gray>Hint: flush reported success but the key survived — FileStore::flush() never verifies deletion.');
            $this->line('  <fg=gray>Most likely cause: storage/framework/cache files are owned by the web user (www), so CLI cannot delete them.');
            $this->line('  <fg=gray>Fix: sudo chown -R www:www storage bootstrap/cache  — then: sudo -u www php artisan cache:clear</>');
        }
    }

    /** @return array<string, int> */
    private function globalCleanlinessScan(): array
    {
        $this->line('  <fg=yellow>4) Global database cleanliness scan</>');

        $braceGloss = (int) DB::table('arabic_concordance')
            ->where('gloss_en', 'like', '%{%')->orWhere('gloss_en', 'like', '%}%')->count();
        $semiGloss = (int) DB::table('arabic_concordance')
            ->where('gloss_en', 'like', '%;%')->count();
        $brokenDeriv = (int) DB::table('lexicons')
            ->where('word_origin', 'like', '%variation of ;%')
            ->orWhere('word_origin', 'like', '%from ;%')
            ->orWhere('word_origin', 'like', '%from the same as ;%')
            ->count();

        $this->table(
            ['Scope', 'Pattern', 'Rows'],
            [
                ['arabic_concordance.gloss_en', '{ or }', (string) $braceGloss],
                ['arabic_concordance.gloss_en', '; (raw semicolon)', (string) $semiGloss],
                ['lexicons.word_origin', 'variation of ; / from ; / from the same as ;', (string) $brokenDeriv],
            ]
        );

        if ($braceGloss + $semiGloss + $brokenDeriv > 0) {
            $this->components->warn('Database is dirty — --fix recommended.');
        } else {
            $this->components->info('Database fully clean.');
        }

        return ['brace_gloss' => $braceGloss, 'semi_gloss' => $semiGloss, 'broken_deriv' => $brokenDeriv];
    }

    // ═══════════════════ PHASE 2 ═══════════════════

    /** @return array<string, int> */
    private function fixGlosses(): array
    {
        $this->line('  <fg=yellow>1) Purge curly braces & raw semicolons (arabic_concordance)</>');

        $a1 = DB::update(
            "UPDATE arabic_concordance SET gloss_en = REPLACE(REPLACE(gloss_en, '{', ''), '}', '') WHERE gloss_en LIKE '%{%' OR gloss_en LIKE '%}%'"
        );
        $a2 = DB::update(
            "UPDATE arabic_concordance SET gloss_en = REPLACE(gloss_en, ';', ' / ') WHERE gloss_en LIKE '%;%'"
        );
        $a3 = DB::update(
            "UPDATE arabic_concordance SET all_strongs = REPLACE(REPLACE(all_strongs, '{', ''), '}', '') WHERE all_strongs LIKE '%{%' OR all_strongs LIKE '%}%'"
        );
        $a4 = DB::update(
            "UPDATE arabic_concordance SET source_word = REPLACE(REPLACE(source_word, '{', ''), '}', '') WHERE source_word LIKE '%{%' OR source_word LIKE '%}%'"
        );

        $this->components->twoColumnDetail('gloss_en braces purged', "<fg=green>{$a1} rows</>");
        $this->components->twoColumnDetail('gloss_en ; → / normalized', "<fg=green>{$a2} rows</>");
        $this->components->twoColumnDetail('all_strongs braces purged', "<fg=green>{$a3} rows</>");
        $this->components->twoColumnDetail('source_word braces purged', "<fg=green>{$a4} rows</>");

        return ['braces' => $a1, 'semicolons' => $a2, 'all_strongs' => $a3, 'source_word' => $a4];
    }

    /** @return array<string, int> */
    private function fixDerivations(): array
    {
        $this->line('  <fg=yellow>2) Fix broken derivations & re-link multi-roots (lexicons)</>');

        // Generic pass first (longest pattern first): collapse dangling
        // preposition+semicolon artifacts left by XML tag stripping.
        // These patterns can never be legitimate English, so the REPLACE is safe.
        $g1 = DB::update(
            "UPDATE lexicons SET word_origin = REPLACE(word_origin, 'from the same as ;', 'from the same as') WHERE word_origin LIKE '%from the same as ;%'"
        );
        $g2 = DB::update(
            "UPDATE lexicons SET word_origin = REPLACE(word_origin, 'variation of ;', 'variation of') WHERE word_origin LIKE '%variation of ;%'"
        );
        $g3 = DB::update(
            "UPDATE lexicons SET word_origin = REPLACE(word_origin, 'from ;', 'from') WHERE word_origin LIKE '%from ;%'"
        );
        // Collapse punctuation wreckage the generic pass may leave behind.
        $g4 = DB::update(
            "UPDATE lexicons SET word_origin = REPLACE(word_origin, '  ', ' ') WHERE word_origin LIKE '%  %'"
        );

        $this->components->twoColumnDetail('dangling "from the same as ;" cleaned', "<fg=green>{$g1} rows</>");
        $this->components->twoColumnDetail('dangling "variation of ;" cleaned', "<fg=green>{$g2} rows</>");
        $this->components->twoColumnDetail('dangling "from ;" cleaned', "<fg=green>{$g3} rows</>");
        $this->components->twoColumnDetail('double spaces collapsed', "<fg=green>{$g4} rows</>");

        // Specific re-link for H1255 — the only entry whose missing root ID is verified.
        // NOTE: roots_json is built from the LIVE H4757 record, never hardcoded,
        // so lemma/transliteration always match the database. arabic_gloss is taken
        // from H4757.short_gloss_ar when translated, else preserved from the old
        // roots_json (never invented).
        $relinked = $this->relinkH1255();

        $remaining = (int) DB::table('lexicons')
            ->where('word_origin', 'like', '%variation of ;%')
            ->orWhere('word_origin', 'like', '%from ;%')
            ->orWhere('word_origin', 'like', '%from the same as ;%')
            ->count();
        $this->components->twoColumnDetail('remaining dangling derivations', $remaining === 0 ? '<fg=green>0</>' : "<fg=red>{$remaining}</>");

        return ['generic' => $g1 + $g2 + $g3 + $g4, 'h1255_relinked' => $relinked, 'remaining' => $remaining];
    }

    private function relinkH1255(): int
    {
        $h1255 = Lexicon::where('strong_id', 'H1255')->first();

        if ($h1255 === null) {
            $this->components->error('H1255 missing — cannot re-link.');

            return 0;
        }

        $origin = (string) $h1255->word_origin;
        $needsTextFix = str_contains($origin, 'variation of ;') || ! str_contains(strtoupper($origin), 'H4757');
        $needsRootsFix = $this->rootsJsonHasNoId($h1255, 'H4757');

        if (! $needsTextFix && ! $needsRootsFix) {
            $this->line('  H1255 already linked — no change.');

            return 0;
        }

        $root = Lexicon::where('strong_id', 'H4757')->first();

        if ($root === null) {
            $this->components->error('H4757 NOT FOUND — word_origin text fixed, roots_json left empty. Re-import H4757 ASAP.');
            $h1255->update(['word_origin' => 'a variation of H4757;', 'root_strongs' => 'H4757']);

            return 1;
        }

        $arabicGloss = $root->short_gloss_ar
            ?? $this->existingRootsJsonGloss($h1255, 'H4757');

        $rootsJson = [[
            'strong_id' => 'H4757',
            'lemma' => $root->lemma,
            'translit' => $root->transliteration,
            'arabic_gloss' => $arabicGloss,
        ]];

        $h1255->update([
            'word_origin' => 'a variation of H4757;',
            'root_strongs' => 'H4757',
        ]);

        // Write roots_json via the query builder (not the model) so the JSON
        // keeps JSON_UNESCAPED_UNICODE formatting instead of \uXXXX escapes.
        DB::table('lexicons')->where('strong_id', 'H1255')->update([
            'roots_json' => json_encode($rootsJson, JSON_UNESCAPED_UNICODE),
        ]);

        $this->line('  H1255 re-linked → H4757 ('.$root->lemma.')');

        return 1;
    }

    private function clearFrameworkCache(): void
    {
        $this->line('  <fg=yellow>3) Clear framework cache</>');
        Cache::flush();
        $this->call('view:clear');
        $this->call('route:clear');
        $this->components->twoColumnDetail('Cache::flush + view:clear + route:clear', '<fg=green>done</>');
    }

    /** @param array<string, int> $report */
    private function printFixSummary(array $report): void
    {
        $this->newLine();
        $this->table(
            ['Repair', 'Affected rows'],
            [
                ['gloss_en braces {} purged', (string) $report['gloss']['braces']],
                ['gloss_en ; → / ', (string) $report['gloss']['semicolons']],
                ['all_strongs braces purged', (string) $report['gloss']['all_strongs']],
                ['source_word braces purged', (string) $report['gloss']['source_word']],
                ['derivation punctuation cleaned', (string) $report['deriv']['generic']],
                ['H1255 re-linked', (string) $report['deriv']['h1255_relinked']],
            ]
        );
    }

    // ═══════════════════ Verification ═══════════════════

    /** @param array<string, string|null> $before */
    private function verifyH1255(array $before): void
    {
        $this->line('  <fg=yellow>H1255 — /lexicon/H1255 "أصل الكلمة وجذرها"</>');

        $row = Lexicon::where('strong_id', 'H1255')->first();

        $this->table(
            ['Column', 'BEFORE', 'AFTER'],
            [
                ['word_origin', (string) ($before['word_origin'] ?? '—'), (string) $row?->word_origin],
                ['root_strongs', (string) ($before['root_strongs'] ?? '—'), (string) $row?->root_strongs],
                [
                    'roots_json → root IDs',
                    implode(',', $this->extractRootIds((string) ($before['roots_json'] ?? ''))),
                    implode(',', $row ? $row->resolveRootIds() : []),
                ],
            ]
        );

        $ok = $row && in_array('H4757', $row->resolveRootIds(), true)
            && str_contains(strtoupper((string) $row->word_origin), 'H4757');

        $this->line($ok
            ? '  <fg=green>✅ H1255 FIXED — derivation displays "a variation of H4757" with linked root.</>'
            : '  <fg=red>❌ H1255 STILL BROKEN — manual review required.</>');
    }

    /** @param array<string, string|null> $before */
    private function verifyGen11(array $before): void
    {
        $this->line('  <fg=yellow>Gen.1.1 word 1 — /verse/Gen.1.1 "فِي الْبَدْءِ"</>');

        $tok = ArabicConcordance::where('verse_osis', 'Gen.1.1')->where('word_pos', 1)->first();
        $after = (string) $tok?->gloss_en;

        $this->table(
            ['Column', 'BEFORE', 'AFTER'],
            [
                ['gloss_en', (string) ($before['gloss_en'] ?? '—'), $after],
            ]
        );

        $ok = $after !== '' && ! str_contains($after, '{') && ! str_contains($after, '}') && ! str_contains($after, ';');

        $this->line($ok
            ? "  <fg=green>✅ Gen.1.1 FIXED — gloss displays clean '{$after}'.</>"
            : '  <fg=red>❌ Gen.1.1 STILL DIRTY — manual review required.</>');
    }

    // ═══════════════════ Helpers ═══════════════════

    private function rootsJsonHasNoId(Lexicon $lexicon, string $strongId): bool
    {
        return ! in_array(strtoupper($strongId), $lexicon->resolveRootIds(), true);
    }

    private function existingRootsJsonGloss(Lexicon $lexicon, string $strongId): ?string
    {
        $raw = $lexicon->getAttribute('roots_json');

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
        } elseif (is_array($raw)) {
            $decoded = $raw;
        } else {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        foreach ($decoded as $item) {
            if (is_array($item)
                && isset($item['strong_id'])
                && strtoupper((string) $item['strong_id']) === strtoupper($strongId)
                && isset($item['arabic_gloss'])
                && is_string($item['arabic_gloss'])
                && trim($item['arabic_gloss']) !== ''
            ) {
                return $item['arabic_gloss'];
            }
        }

        return null;
    }

    /** @return string[] */
    private function extractRootIds(string $rootsJson): array
    {
        $decoded = json_decode($rootsJson, true);

        if (! is_array($decoded)) {
            return [];
        }

        $ids = [];
        foreach ($decoded as $item) {
            if (is_array($item) && isset($item['strong_id'])) {
                $ids[] = strtoupper((string) $item['strong_id']);
            } elseif (is_string($item) && trim($item) !== '') {
                $ids[] = strtoupper(trim($item));
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function truncate(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max - 1).'…' : $value;
    }
}
