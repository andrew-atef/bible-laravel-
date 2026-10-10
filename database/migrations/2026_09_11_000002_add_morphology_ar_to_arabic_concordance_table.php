<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add Arabic morphology parsing (حرف جر + اسم...).
     * Idempotent.
     */
    public function up(): void
    {
        if (! Schema::hasTable('arabic_concordance')) {
            return;
        }

        if (! Schema::hasColumn('arabic_concordance', 'morphology_ar')) {
            Schema::table('arabic_concordance', function (Blueprint $table): void {
                $table->text('morphology_ar')->nullable()->after('morphology')->comment('Arabic parse حرف جر + ...');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('arabic_concordance', 'morphology_ar')) {
            Schema::table('arabic_concordance', function (Blueprint $table): void {
                $table->dropColumn('morphology_ar');
            });
        }
    }
};
