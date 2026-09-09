<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lexicons', function (Blueprint $table) {
            $table->text('strongs_definition_ar')->nullable()->after('strongs_definition');
            $table->text('kjv_translations_ar')->nullable()->after('kjv_translations');
            $table->json('outline_json_ar')->nullable()->after('outline_json');
            $table->string('short_gloss_ar', 255)->nullable()->after('kjv_translations_ar');
            $table->timestamp('translated_at')->nullable()->after('is_indexed');
        });
    }

    public function down(): void
    {
        Schema::table('lexicons', function (Blueprint $table) {
            $table->dropColumn([
                'strongs_definition_ar',
                'kjv_translations_ar',
                'outline_json_ar',
                'short_gloss_ar',
                'translated_at',
            ]);
        });
    }
};
