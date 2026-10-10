<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * LXX concordance bridge: Strong → Hebrew counterpart via Septuagint.
     * Idempotent — CREATE IF NOT EXISTS semantics via hasTable guard.
     */
    public function up(): void
    {
        if (Schema::hasTable('lxx_concordance')) {
            // Ensure indexes exist
            try {
                Illuminate\Support\Facades\DB::statement('CREATE INDEX IF NOT EXISTS idx_lxx_strong ON lxx_concordance(strong_id)');
                Illuminate\Support\Facades\DB::statement('CREATE INDEX IF NOT EXISTS idx_lxx_verse ON lxx_concordance(verse_osis)');
                Illuminate\Support\Facades\DB::statement('CREATE INDEX IF NOT EXISTS idx_lxx_hebrew_strong ON lxx_concordance(hebrew_strong)');
            } catch (Throwable $e) {
            }
            return;
        }

        Schema::create('lxx_concordance', function (Blueprint $table): void {
            $table->id();
            $table->string('strong_id', 10)->nullable()->index('idx_lxx_strong')->comment('Greek Strong e.g. G4647');
            $table->string('verse_osis', 20)->nullable()->index('idx_lxx_verse')->comment('OSIS e.g. Gen.1.1');
            $table->text('hebrew_word')->nullable()->comment('Hebrew word in LXX context');
            $table->string('hebrew_strong', 10)->nullable()->index('idx_lxx_hebrew_strong');
            $table->text('context_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lxx_concordance');
    }
};
