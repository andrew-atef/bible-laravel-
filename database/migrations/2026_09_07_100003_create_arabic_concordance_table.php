<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * arabic_concordance: word-level alignment (382k+ tokens). Idempotent for existing DB.
     */
    public function up(): void
    {
        if (Schema::hasTable('arabic_concordance')) {
            // Ensure required indexes exist (python pipeline created them, but verify)
            $indexes = ['idx_conc_verse', 'idx_conc_strong', 'idx_verse_pos'];
            foreach ($indexes as $idx) {
                // Schema::hasIndex not available on SQLite for named indexes reliably; use raw check
            }
            // Ensure indexes via raw statements (IF NOT EXISTS)
            try {
                \Illuminate\Support\Facades\DB::statement('CREATE INDEX IF NOT EXISTS idx_conc_verse ON arabic_concordance(verse_osis)');
                \Illuminate\Support\Facades\DB::statement('CREATE INDEX IF NOT EXISTS idx_conc_strong ON arabic_concordance(strong_id)');
                \Illuminate\Support\Facades\DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS idx_verse_pos ON arabic_concordance(verse_osis, word_pos)');
            } catch (\Throwable $e) {
            }

            return;
        }

        Schema::create('arabic_concordance', function (Blueprint $table): void {
            $table->id();
            $table->string('verse_osis', 20)->index('idx_conc_verse')->comment('OSIS ref, e.g. Gen.1.1');
            $table->unsignedInteger('word_pos')->comment('Position in verse (1-indexed)');
            $table->string('strong_id', 10)->index('idx_conc_strong')->comment('Primary Strong ID H/G <9000');
            $table->text('source_word')->comment('Vocalized original word');
            $table->text('source_clean')->comment('Diacritics stripped');
            $table->string('transliteration', 255)->comment('Latin phonetic');
            $table->text('arabic_word')->comment('Vocalized SVD Arabic');
            $table->text('arabic_clean')->comment('Stripped Tashkeel');
            $table->text('gloss_en')->nullable();
            $table->string('morphology', 255)->nullable()->comment('Grammar parsing');
            $table->text('all_strongs')->nullable()->comment('Full raw IDs e.g. H9003;H7225');
            $table->timestamps();

            $table->unique(['verse_osis', 'word_pos'], 'idx_verse_pos');
            $table->index(['verse_osis', 'strong_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arabic_concordance');
    }
};
