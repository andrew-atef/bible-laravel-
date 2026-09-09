<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Verse model - canonical verse spine (Gen.1.1 etc.)
 *
 * @property int $id
 * @property int $book_id
 * @property int $chapter
 * @property int $verse
 * @property string $osis_id e.g. Gen.1.1
 */
class Verse extends Model
{
    use HasFactory;

    /**
     * @var string[]
     */
    protected $fillable = [
        'book_id',
        'chapter',
        'verse',
        'osis_id',
        'is_indexed',
        'text_ar',
        'text_clean',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'book_id' => 'integer',
        'chapter' => 'integer',
        'verse' => 'integer',
        'is_indexed' => 'boolean',
    ];

    /**
     * Book this verse belongs to.
     *
     * @return BelongsTo<Book, Verse>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'book_id', 'id');
    }

    /**
     * Word-level Arabic concordance tokens for this verse.
     * Foreign key is OSIS string (verse_osis -> osis_id).
     *
     * @return HasMany<ArabicConcordance>
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(ArabicConcordance::class, 'verse_osis', 'osis_id')
            ->orderBy('word_pos');
    }

    /**
     * Alias for tokens() - explicit naming for lexical platform.
     *
     * @return HasMany<ArabicConcordance>
     */
    public function arabicTokens(): HasMany
    {
        return $this->hasMany(ArabicConcordance::class, 'verse_osis', 'osis_id')
            ->orderBy('word_pos');
    }

    /**
     * Scope by exact OSIS ID.
     *
     * @param Builder<Verse> $query
     */
    public function scopeByOsis(Builder $query, string $osis): Builder
    {
        return $query->where('osis_id', $osis);
    }

    /**
     * Get next verse in canonical order.
     * Attempts book/chapter/verse increment, fallback to id ordering.
     */
    public function nextVerse(): ?self
    {
        // Try same book next chapter/verse via id ordering (canonical insertion order)
        // More robust: order by book_number, chapter, verse via join
        $next = self::where(function (Builder $q): void {
            $q->where('book_id', '>', $this->book_id)
                ->orWhere(function (Builder $q): void {
                    $q->where('book_id', $this->book_id)
                        ->where(function (Builder $q): void {
                            $q->where('chapter', '>', $this->chapter)
                                ->orWhere(function (Builder $q): void {
                                    $q->where('chapter', $this->chapter)
                                        ->where('verse', '>', $this->verse);
                                });
                        });
                });
        })
            ->orderBy('book_id')
            ->orderBy('chapter')
            ->orderBy('verse')
            ->first();

        // Fallback to simple id increment (covers gaps)
        if ($next === null) {
            $next = self::where('id', '>', $this->id)->orderBy('id')->first();
        }

        return $next;
    }

    /**
     * Get previous verse in canonical order.
     */
    public function previousVerse(): ?self
    {
        $prev = self::where(function (Builder $q): void {
            $q->where('book_id', '<', $this->book_id)
                ->orWhere(function (Builder $q): void {
                    $q->where('book_id', $this->book_id)
                        ->where(function (Builder $q): void {
                            $q->where('chapter', '<', $this->chapter)
                                ->orWhere(function (Builder $q): void {
                                    $q->where('chapter', $this->chapter)
                                        ->where('verse', '<', $this->verse);
                                });
                        });
                });
        })
            ->orderByDesc('book_id')
            ->orderByDesc('chapter')
            ->orderByDesc('verse')
            ->first();

        if ($prev === null) {
            $prev = self::where('id', '<', $this->id)->orderByDesc('id')->first();
        }

        return $prev;
    }
}
