<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add multi-root support: root_strongs (string list) + roots_json (JSON array).
     * Idempotent — safe if columns already added via sqlite3.
     */
    public function up(): void
    {
        if (! Schema::hasTable('lexicons')) {
            return;
        }

        if (! Schema::hasColumn('lexicons', 'root_strongs')) {
            Schema::table('lexicons', function (Blueprint $table): void {
                $table->text('root_strongs')->nullable()->after('root_strong')->comment('Semicolon list e.g. G4628;G3700');
            });
        }

        if (! Schema::hasColumn('lexicons', 'roots_json')) {
            Schema::table('lexicons', function (Blueprint $table): void {
                $table->json('roots_json')->nullable()->after('root_strongs')->comment('JSON array ["G4628","G3700"]');
            });
        }

        // Helpful indexes for multi-root lookups
        try {
            Illuminate\Support\Facades\DB::statement('CREATE INDEX IF NOT EXISTS idx_lex_root_strongs ON lexicons(root_strongs)');
            Illuminate\Support\Facades\DB::statement('CREATE INDEX IF NOT EXISTS idx_lex_roots_json ON lexicons(roots_json)');
        } catch (Throwable $e) {
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('lexicons', 'roots_json')) {
            Schema::table('lexicons', function (Blueprint $table): void {
                $table->dropColumn('roots_json');
            });
        }
        if (Schema::hasColumn('lexicons', 'root_strongs')) {
            Schema::table('lexicons', function (Blueprint $table): void {
                $table->dropColumn('root_strongs');
            });
        }
    }
};
