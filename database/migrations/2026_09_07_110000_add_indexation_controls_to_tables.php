<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // books.is_indexed
        if (Schema::hasTable('books')) {
            Schema::table('books', function (Blueprint $table): void {
                if (! Schema::hasColumn('books', 'is_indexed')) {
                    $table->boolean('is_indexed')->default(false)->after('slug');
                }
            });
        }

        // verses.is_indexed + index
        if (Schema::hasTable('verses')) {
            Schema::table('verses', function (Blueprint $table): void {
                if (! Schema::hasColumn('verses', 'is_indexed')) {
                    $table->boolean('is_indexed')->default(false)->after('osis_id');
                }
            });
            // SQLite: ensure index exists (Laravel will create if not exists via separate statement)
            try {
                Schema::table('verses', function (Blueprint $table): void {
                    $table->index('is_indexed', 'verses_is_indexed_index');
                });
            } catch (\Throwable $e) {
                // index may already exist
            }
        }

        // lexicons.is_indexed + index
        if (Schema::hasTable('lexicons')) {
            Schema::table('lexicons', function (Blueprint $table): void {
                if (! Schema::hasColumn('lexicons', 'is_indexed')) {
                    $table->boolean('is_indexed')->default(false)->after('outline_json');
                }
            });
            try {
                Schema::table('lexicons', function (Blueprint $table): void {
                    $table->index('is_indexed', 'lexicons_is_indexed_index');
                });
            } catch (\Throwable $e) {
            }
        }

        // Ensure SQLite fallback indexes via raw when Schema::hasIndex not available
        if (config('database.default') === 'sqlite') {
            try {
                \Illuminate\Support\Facades\DB::statement('CREATE INDEX IF NOT EXISTS verses_is_indexed_index ON verses(is_indexed)');
                \Illuminate\Support\Facades\DB::statement('CREATE INDEX IF NOT EXISTS lexicons_is_indexed_index ON lexicons(is_indexed)');
                \Illuminate\Support\Facades\DB::statement('CREATE INDEX IF NOT EXISTS books_is_indexed_index ON books(is_indexed)');
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('books') && Schema::hasColumn('books', 'is_indexed')) {
            Schema::table('books', function (Blueprint $table): void {
                $table->dropColumn('is_indexed');
            });
        }
        if (Schema::hasTable('verses') && Schema::hasColumn('verses', 'is_indexed')) {
            Schema::table('verses', function (Blueprint $table): void {
                try {
                    $table->dropIndex('verses_is_indexed_index');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('is_indexed');
            });
        }
        if (Schema::hasTable('lexicons') && Schema::hasColumn('lexicons', 'is_indexed')) {
            Schema::table('lexicons', function (Blueprint $table): void {
                try {
                    $table->dropIndex('lexicons_is_indexed_index');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('is_indexed');
            });
        }
    }
};
