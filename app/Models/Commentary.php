<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Commentary - layered exegesis (passage / verse / word)
 *
 * @property int $id
 * @property string $author_name
 * @property string|null $era
 * @property string $verse_start Indexed OSIS start
 * @property string|null $verse_end OSIS end for passage-level
 * @property string|null $target_phrase
 * @property string|null $strong_id Indexed lexical hook
 * @property string $level passage|verse|word
 * @property string|null $title
 * @property string|null $body_html
 * @property string|null $source_book
 */
class Commentary extends Model
{
    use HasFactory;

    /**
     * @var string[]
     */
    protected $fillable = [
        'author_name',
        'era',
        'verse_start',
        'verse_end',
        'target_phrase',
        'strong_id',
        'level',
        'title',
        'body_html',
        'source_book',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'level' => 'string',
    ];

    /**
     * Optional lexical link.
     *
     * @return BelongsTo<Lexicon, Commentary>
     */
    public function lexicon(): BelongsTo
    {
        return $this->belongsTo(Lexicon::class, 'strong_id', 'strong_id');
    }

    /**
     * Scope entries that cover a given verse OSIS.
     * Matches exact verse_start or passage range containing the verse.
     *
     * Note: OSIS string comparison is lexical; for true range, use
     * verse_start <= :osis AND (verse_end IS NULL OR verse_end >= :osis)
     * Requires books/chapters numeric ordering for perfect accuracy, but
     * for most usages exact match is sufficient. We provide both.
     *
     * @param Builder<Commentary> $query
     */
    public function scopeForVerse(Builder $query, string $osis): Builder
    {
        return $query->where(function (Builder $q) use ($osis): void {
            $q->where('verse_start', $osis)
                ->orWhere(function (Builder $q) use ($osis): void {
                    $q->where('verse_start', '<=', $osis)
                        ->where(function (Builder $q) use ($osis): void {
                            $q->whereNull('verse_end')
                                ->orWhere('verse_end', '>=', $osis);
                        });
                });
        });
    }

    /**
     * Scope by Strong ID hook.
     *
     * @param Builder<Commentary> $query
     */
    public function scopeForStrong(Builder $query, string $strongId): Builder
    {
        return $query->where('strong_id', $strongId);
    }

    /**
     * Scope by level.
     *
     * @param Builder<Commentary> $query
     * @param string $level passage|verse|word
     */
    public function scopeByLevel(Builder $query, string $level): Builder
    {
        return $query->where('level', $level);
    }
}
