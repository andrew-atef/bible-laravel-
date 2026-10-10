<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lexicon — Strong's Lexicon (strict schema: biblical_core.db)
 *
 * Real columns ONLY: strong_id, language, lemma, lemma_clean, transliteration,
 * pronunciation, part_of_speech, word_origin, root_strongs, roots_json,
 * strongs_definition, kjv_translations, outline_json, is_indexed
 *
 * @property string $strong_id PK e.g. H7225, G4647
 * @property string $language hebrew|aramaic|greek
 * @property string $lemma Vocalized lemma
 * @property string $lemma_clean Stripped for search
 * @property string|null $transliteration
 * @property string|null $pronunciation
 * @property string|null $part_of_speech
 * @property string|null $word_origin Raw derivation text
 * @property string|null $root_strongs Semicolon list e.g. G4628;G3700
 * @property array|null $roots_json JSON array ["G4628","G3700"]
 * @property string|null $strongs_definition English definition
 * @property string|null $kjv_translations KJV tags
 * @property array|null $outline_json JSON array
 * @property bool $is_indexed
 */
class Lexicon extends Model
{
    use HasFactory;

    protected $table = 'lexicons';

    protected $primaryKey = 'strong_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /** @var string[] */
    protected $fillable = [
        'strong_id',
        'language',
        'lemma',
        'lemma_clean',
        'transliteration',
        'pronunciation',
        'part_of_speech',
        'word_origin',
        'root_strongs',
        'roots_json',
        'strongs_definition',
        'kjv_translations',
        'outline_json',
        'is_indexed',
        'strongs_definition_ar',
        'kjv_translations_ar',
        'outline_json_ar',
        'short_gloss_ar',
        'translated_at',
    ];

    protected function casts(): array
    {
        return [
            'roots_json' => 'array',
            'outline_json' => 'array',
            'outline_json_ar' => 'array',
            'is_indexed' => 'boolean',
            'translated_at' => 'datetime',
        ];
    }

    /** @return HasMany<ArabicConcordance> */
    public function concordances(): HasMany
    {
        return $this->hasMany(ArabicConcordance::class, 'strong_id', 'strong_id');
    }

    /** @return HasMany<ArabicConcordance> */
    public function tokens(): HasMany
    {
        return $this->hasMany(ArabicConcordance::class, 'strong_id', 'strong_id');
    }

    /** @return HasMany<LxxConcordance> */
    public function lxxConcordances(): HasMany
    {
        return $this->hasMany(LxxConcordance::class, 'strong_id', 'strong_id');
    }

    /** @return HasMany<LxxConcordance> */
    public function lxxOccurrences(): HasMany
    {
        return $this->hasMany(LxxConcordance::class, 'strong_id', 'strong_id');
    }

    /**
     * All root lexicons derived from roots_json / root_strongs (strict schema).
     * @return \Illuminate\Support\Collection<int, Lexicon>
     */
    public function rootLexicons(): \Illuminate\Support\Collection
    {
        $ids = $this->resolveRootIds();
        if ($ids === []) {
            return collect();
        }
        return self::whereIn('strong_id', $ids)->get()->sortBy(fn (self $l) => array_search($l->strong_id, $ids, true))->values();
    }

    /** 
     * Resolve all root Strong IDs for this entry (multi-root aware)
     * Handles both plain string arrays ["G4628"] and rich object arrays [{"strong_id": "G4628", ...}]
     * 
     * @return string[] 
     */
    public function resolveRootIds(): array
    {
        if (is_array($this->roots_json) && $this->roots_json !== []) {
            $ids = [];
            foreach ($this->roots_json as $item) {
                if (is_array($item) && isset($item['strong_id'])) {
                    // لو الداتا بالشكل الغني الجديد
                    $ids[] = strtoupper(trim((string) $item['strong_id']));
                } elseif (is_string($item) && trim($item) !== '') {
                    // لو الداتا بالشكل القديم كنص عادي
                    $ids[] = strtoupper(trim($item));
                }
            }
            if (!empty($ids)) {
                return array_values(array_unique(array_filter($ids)));
            }
        }

        $raw = $this->getAttribute('root_strongs');
        if (is_string($raw) && trim($raw) !== '') {
            preg_match_all('/[HG]\d+/i', $raw, $m);
            if (!empty($m[0])) {
                return array_values(array_unique(array_map(fn ($v) => strtoupper($v), $m[0])));
            }
        }

        return [];
    }

    /** Children via roots_json / root_strongs — strict schema only */
    public function derivedLexiconsMulti(): Builder
    {
        $id = $this->strong_id;
        return self::query()->where(function (Builder $q) use ($id): void {
            $q->where('root_strongs', 'like', "%{$id}%")
                ->orWhere('roots_json', 'like', "%\"{$id}\"%");
        });
    }

    public function scopeByLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    public function scopeByPrefix(Builder $query, string $prefix): Builder
    {
        return $query->where('strong_id', 'like', $prefix . '%');
    }

    public function scopeIndexed(Builder $query): Builder
    {
        return $query->where('is_indexed', true);
    }
}
