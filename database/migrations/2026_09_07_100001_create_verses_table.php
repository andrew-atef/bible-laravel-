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
     * Verses table: canonical verse spine (book + chapter + verse -> osis_id)
     */
    public function up(): void
    {
        if (Schema::hasTable('verses')) {
            return;
        }

        Schema::create('verses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedSmallInteger('chapter')->comment('Chapter number');
            $table->unsignedSmallInteger('verse')->comment('Verse number');
            $table->string('osis_id', 20)->unique()->comment('OSIS reference, e.g. Gen.1.1');
            $table->timestamps();

            $table->index(['book_id', 'chapter', 'verse']);
            $table->index('osis_id');
            // Ensure no duplicate chapter/verse per book
            $table->unique(['book_id', 'chapter', 'verse'], 'verses_book_chapter_verse_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verses');
    }
};
