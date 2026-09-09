<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ArabicConcordance (WordToken) - word-level alignment
 *
 * Maps each Arabic word (SVD) to its source Hebrew/Greek lemma.
 *
 * @property int $id
 * @property string $verse_osis e.g. Gen.1.1 (Indexed)
 * @property int $word_pos
 * @property string $strong_id Primary lexical Strong (H7225)
 * @property string $source_word Vocalized original (בְּרֵאשִׁ֖ית)
 * @property string $source_clean Stripped
 * @property string $transliteration
 * @property string $arabic_word Vocalized SVD Arabic (فِي الْبَدْءِ)
 * @property string $arabic_clean Stripped Tashkeel
 * @property string|null $gloss_en
 * @property string|null $morphology Grammar parsing
 * @property string|null $all_strongs Full raw IDs (H9003;H7225)
 */
class ArabicConcordance extends Model
{
    use HasFactory;

    /**
     * Explicit table name.
     *
     * @var string
     */
    protected $table = 'arabic_concordance';

    /**
     * No timestamps in pipeline; disable.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string[]
     */
    protected $fillable = [
        'verse_osis',
        'word_pos',
        'strong_id',
        'source_word',
        'source_clean',
        'transliteration',
        'arabic_word',
        'arabic_clean',
        'gloss_en',
        'morphology',
        'all_strongs',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'word_pos' => 'integer',
    ];

    /**
     * Lexicon entry for this token's Strong ID.
     *
     * @return BelongsTo<Lexicon, ArabicConcordance>
     */
    public function lexicon(): BelongsTo
    {
        return $this->belongsTo(Lexicon::class, 'strong_id', 'strong_id');
    }

    /**
     * Verse (canonical spine) this token belongs to.
     * Verse.osis_id <-> ArabicConcordance.verse_osis (string FK, not integer)
     *
     * @return BelongsTo<Verse, ArabicConcordance>
     */
    public function verse(): BelongsTo
    {
        return $this->belongsTo(Verse::class, 'verse_osis', 'osis_id');
    }

    /**
     * Scope by verse OSIS.
     *
     * @param Builder<ArabicConcordance> $query
     */
    public function scopeByVerse(Builder $query, string $osis): Builder
    {
        return $query->where('verse_osis', $osis)->orderBy('word_pos');
    }

    /**
     * Scope by Strong ID.
     *
     * @param Builder<ArabicConcordance> $query
     */
    public function scopeByStrong(Builder $query, string $strongId): Builder
    {
        return $query->where('strong_id', $strongId);
    }

    /**
     * Scope by Arabic clean form (search without Tashkeel).
     *
     * @param Builder<ArabicConcordance> $query
     */
    public function scopeByArabic(Builder $query, string $arabicClean): Builder
    {
        return $query->where('arabic_clean', $arabicClean);
    }
}
