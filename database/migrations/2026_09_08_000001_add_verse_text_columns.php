<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('verses')) {
            Schema::table('verses', function (Blueprint $table): void {
                if (! Schema::hasColumn('verses', 'text_ar')) {
                    $table->text('text_ar')->nullable()->after('osis_id');
                }
                if (! Schema::hasColumn('verses', 'text_clean')) {
                    $table->text('text_clean')->nullable()->after('text_ar');
                }
            });

            // Indexes for fast full-text search
            try {
                Schema::table('verses', function (Blueprint $table): void {
                    $table->index('text_clean', 'idx_verse_text_clean');
                    $table->index('text_ar', 'idx_verse_text_ar');
                });
            } catch (\Throwable $e) {
                // Fallback raw
                try {
                    \Illuminate\Support\Facades\DB::statement('CREATE INDEX IF NOT EXISTS idx_verse_text_clean ON verses(text_clean)');
                    \Illuminate\Support\Facades\DB::statement('CREATE INDEX IF NOT EXISTS idx_verse_text_ar ON verses(text_ar)');
                } catch (\Throwable $e2) {
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('verses')) {
            Schema::table('verses', function (Blueprint $table): void {
                if (Schema::hasColumn('verses', 'text_ar')) {
                    $table->dropColumn('text_ar');
                }
                if (Schema::hasColumn('verses', 'text_clean')) {
                    $table->dropColumn('text_clean');
                }
            });
        }
    }
};
