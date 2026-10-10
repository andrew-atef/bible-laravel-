<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ArabicConcordance (WordToken) — word-level alignment
 *
 * Maps each Arabic word (SVD) to its source Hebrew/Greek lemma.
 * Supports new clean schema: morphology_ar (Arabic parse) + morphology (English/computer).
 *
 * @property int $id PK AUTOINCREMENT
 * @property string $verse_osis e.g. Gen.1.1 (Indexed)
 * @property int $word_pos Position in verse (1..n)
 * @property string $strong_id Primary Strong <9000 (Indexed) e.g. H7225
 * @property string|null $all_strongs Full raw IDs e.g. H9003;H7225
 * @property string $source_word Vocalized original בְּרֵאשִׁ֖ית
 * @property string $transliteration Latin be.;re.Shit
 * @property string $arabic_word Vocalized SVD فِي الْبَدْءِ
 * @property string $arabic_clean Stripped في البدء
 * @property string|null $gloss_en English gloss in;{beginning}
 * @property string|null $morphology English/computer parse HR/Ncfsa
 * @property string|null $morphology_ar Arabic parse حرف جر + اسم مؤنث
 */
class ArabicConcordance extends Model
{
    use HasFactory;

    protected $table = 'arabic_concordance';

    public $timestamps = false;

    /**
     * @var string[]
     */
    protected $fillable = [
        'verse_osis',
        'word_pos',
        'strong_id',
        'all_strongs',
        'source_word',
        'source_clean',
        'transliteration',
        'arabic_word',
        'arabic_clean',
        'gloss_en',
        'morphology',
        'morphology_ar',
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
     * FK is OSIS string (verse_osis -> osis_id).
     *
     * @return BelongsTo<Verse, ArabicConcordance>
     */
    public function verse(): BelongsTo
    {
        return $this->belongsTo(Verse::class, 'verse_osis', 'osis_id');
    }

    /**
     * LXX cross-reference for this token if Strong is Greek and has LXX link.
     * Note: lxx_concordance is keyed by strong_id + verse_osis, not by word_pos.
     *
     * @return BelongsTo<LxxConcordance, ArabicConcordance>
     */
    public function lxx(): BelongsTo
    {
        return $this->belongsTo(LxxConcordance::class, 'strong_id', 'strong_id')
            ->whereColumn('lxx_concordance.verse_osis', 'arabic_concordance.verse_osis');
    }

    public function scopeByVerse(Builder $query, string $osis): Builder
    {
        return $query->where('verse_osis', $osis)->orderBy('word_pos');
    }

    public function scopeByStrong(Builder $query, string $strongId): Builder
    {
        return $query->where('strong_id', $strongId);
    }

    public function scopeByArabic(Builder $query, string $arabicClean): Builder
    {
        return $query->where('arabic_clean', $arabicClean);
    }

    public function scopeIndexedVerse(Builder $query): Builder
    {
        return $query->whereHas('verse', fn (Builder $q) => $q->where('is_indexed', true));
    }
}
