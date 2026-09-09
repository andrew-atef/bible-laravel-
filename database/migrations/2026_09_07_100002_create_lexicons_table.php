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
     * Lexicons table: Strong's lexicon (H1-H8674, G1-G5624). Idempotent - preserves existing biblical_core.db data.
     */
    public function up(): void
    {
        if (Schema::hasTable('lexicons')) {
            // Table already exists in biblical_core.db (from Python pipeline).
            // Verify indexes exist; if not, add them. Do not recreate/drop to preserve 14k rows.
            if (! Schema::hasIndex('lexicons', 'idx_lex_clean')) {
                try {
                    Schema::table('lexicons', function (Blueprint $table): void {
                        $table->index('lemma_clean', 'idx_lex_clean');
                    });
                } catch (\Throwable $e) {
                    // index may already exist under different name
                }
            }

            return;
        }

        Schema::create('lexicons', function (Blueprint $table): void {
            $table->string('strong_id', 10)->primary()->comment('Strong ID, e.g. H7225, G2424');
            $table->enum('language', ['hebrew', 'aramaic', 'greek'])->comment('hebrew, aramaic, greek');
            $table->text('lemma')->comment('Vocalized original lemma (בְּרֵאשִׁית, Βίβλος)');
            $table->text('lemma_clean')->comment('Diacritics stripped for search');
            $table->string('transliteration', 255)->nullable();
            $table->string('pronunciation', 255)->nullable();
            $table->string('part_of_speech', 100)->nullable();
            $table->text('word_origin')->nullable();
            $table->string('root_strong', 10)->nullable()->comment('Root Strong ID self-ref');
            $table->text('strongs_definition')->nullable();
            $table->text('kjv_translations')->nullable();
            $table->json('outline_json')->nullable()->comment('JSON array of outline points');
            $table->timestamps();

            $table->index('lemma_clean', 'idx_lex_clean');
            // Foreign key self-ref for root_strong -> lexicons.strong_id (nullable, no cascade)
            // Added via raw statement to avoid SQLite FK timing issues if needed.
        });

        // Add self-referencing FK if supported (SQLite needs table already created)
        try {
            \Illuminate\Support\Facades\DB::statement('CREATE INDEX IF NOT EXISTS idx_lex_root ON lexicons(root_strong)');
        } catch (\Throwable $e) {
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lexicons');
    }
};
