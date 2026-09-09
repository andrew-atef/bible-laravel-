<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lexicon model - Strong's Lexicon (H7225, G2424 etc.)
 *
 * @property string $strong_id Primary key (H7225, G2424)
 * @property string $language hebrew|aramaic|greek
 * @property string $lemma
 * @property string $lemma_clean
 * @property string|null $transliteration
 * @property string|null $pronunciation
 * @property string|null $part_of_speech
 * @property string|null $word_origin
 * @property string|null $root_strong
 * @property string|null $strongs_definition
 * @property string|null $kjv_translations
 * @property array|null $outline_json
 */
class Lexicon extends Model
{
    use HasFactory;

    /**
     * Table is lexicons (default).
     *
     * @var string
     */
    protected $table = 'lexicons';

    /**
     * Primary key is strong_id (string, non-incrementing).
     *
     * @var string
     */
    protected $primaryKey = 'strong_id';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string
     */
    protected $keyType = 'string';

    /**
     * No timestamps in original pipeline; but we support if added via migration.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string[]
     */
    protected $fillable = [
        'strong_id',
        'language',
        'lemma',
        'lemma_clean',
        'transliteration',
        'pronunciation',
        'part_of_speech',
        'word_origin',
        'root_strong',
        'strongs_definition',
        'strongs_definition_ar',
        'kjv_translations',
        'kjv_translations_ar',
        'outline_json',
        'outline_json_ar',
        'short_gloss_ar',
        'is_indexed',
        'translated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outline_json' => 'array',
            'outline_json_ar' => 'array',
            'is_indexed' => 'boolean',
            'translated_at' => 'datetime',
        ];
    }

    /**
     * All Arabic concordance tokens using this Strong ID.
     *
     * @return HasMany<ArabicConcordance>
     */
    public function concordances(): HasMany
    {
        return $this->hasMany(ArabicConcordance::class, 'strong_id', 'strong_id');
    }

    /**
     * Alias: tokens.
     *
     * @return HasMany<ArabicConcordance>
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(ArabicConcordance::class, 'strong_id', 'strong_id');
    }

    /**
     * Root lexicon (self-referential) via root_strong.
     *
     * @return BelongsTo<Lexicon, Lexicon>
     */
    public function rootLexicon(): BelongsTo
    {
        return $this->belongsTo(self::class, 'root_strong', 'strong_id');
    }

    /**
     * Children derived from this root.
     *
     * @return HasMany<Lexicon>
     */
    public function derivedLexicons(): HasMany
    {
        return $this->hasMany(self::class, 'root_strong', 'strong_id');
    }

    /**
     * Scope by language.
     *
     * @param Builder<Lexicon> $query
     */
    public function scopeByLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    /**
     * Scope by Strong ID prefix (H or G).
     *
     * @param Builder<Lexicon> $query
     */
    public function scopeByPrefix(Builder $query, string $prefix): Builder
    {
        return $query->where('strong_id', 'like', $prefix . '%');
    }
}
