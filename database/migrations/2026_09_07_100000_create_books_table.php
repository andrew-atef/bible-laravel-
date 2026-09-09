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
     * Books table: 66 canonical books with OSIS codes and bilingual names.
     */
    public function up(): void
    {
        // Ensure SQLite pragmas for this migration session
        if (config('database.default') === 'sqlite') {
            try {
                \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON');
                \Illuminate\Support\Facades\DB::statement('PRAGMA journal_mode = WAL');
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (Schema::hasTable('books')) {
            return;
        }

        Schema::create('books', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10)->unique()->comment('OSIS code, e.g. Gen, Exod, Mat');
            $table->enum('testament', ['OT', 'NT'])->index()->comment('OT or NT');
            $table->unsignedTinyInteger('book_number')->unique()->comment('1..66 canonical order');
            $table->string('name_ar', 100)->comment('Arabic name, e.g. التكوين');
            $table->string('name_en', 100)->comment('English name, e.g. Genesis');
            $table->string('slug', 100)->unique()->comment('URL slug');
            $table->timestamps();

            $table->index(['testament', 'book_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
