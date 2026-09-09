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
     * commentaries: layered exegesis (passage / verse / word)
     */
    public function up(): void
    {
        if (Schema::hasTable('commentaries')) {
            return;
        }

        Schema::create('commentaries', function (Blueprint $table): void {
            $table->id();
            $table->string('author_name', 255)->comment('e.g. Matthew Henry, Ibn ...');
            $table->string('era', 50)->nullable()->comment('Historical era');
            $table->string('verse_start', 20)->index()->comment('OSIS start, e.g. Gen.1.1');
            $table->string('verse_end', 20)->nullable()->comment('OSIS end for passage-level');
            $table->string('target_phrase', 500)->nullable()->comment('Arabic or original phrase target');
            $table->string('strong_id', 10)->nullable()->index()->comment('Lexical hook H/G ID');
            $table->enum('level', ['passage', 'verse', 'word'])->index()->comment('Granularity');
            $table->string('title', 500)->nullable();
            $table->longText('body_html')->nullable()->comment('Rich HTML commentary body (Arabic-first)');
            $table->string('source_book', 255)->nullable()->comment('Source book reference');
            $table->timestamps();

            $table->index(['verse_start', 'verse_end']);
            $table->index(['strong_id', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commentaries');
    }
};
