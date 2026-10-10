<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LxxConcordance — Septuagint (LXX) cross-reference bridge
 *
 * Links a Strong's entry to its Hebrew counterpart via LXX.
 * Example: G4647 (σκόλοψ) → hebrew_word=σκέλος / hebrew_strong=G4628
 *
 * @property int $id PK AUTOINCREMENT
 * @property string $strong_id FK → lexicons.strong_id (e.g. G4647)
 * @property string $verse_osis OSIS reference (e.g. Gen.1.1) FK → verses.osis_id
 * @property string|null $hebrew_word Hebrew word in LXX context
 * @property string|null $hebrew_strong Hebrew Strong ID (e.g. H7523)
 * @property string|null $context_note Additional note
 */
class LxxConcordance extends Model
{
    use HasFactory;

    protected $table = 'lxx_concordance';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    /**
     * @var string[]
     */
    protected $fillable = [
        'strong_id',
        'verse_osis',
        'hebrew_word',
        'hebrew_strong',
        'context_note',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'strong_id' => 'string',
        'verse_osis' => 'string',
    ];

    /**
     * Strong's lexicon entry for this LXX link.
     *
     * @return BelongsTo<Lexicon, LxxConcordance>
     */
    public function lexicon(): BelongsTo
    {
        return $this->belongsTo(Lexicon::class, 'strong_id', 'strong_id');
    }

    /**
     * Verse where this LXX cross-reference occurs.
     *
     * @return BelongsTo<Verse, LxxConcordance>
     */
    public function verse(): BelongsTo
    {
        return $this->belongsTo(Verse::class, 'verse_osis', 'osis_id');
    }

    /**
     * Hebrew lexicon if hebrew_strong is present (e.g. G4628 → H???).
     *
     * @return BelongsTo<Lexicon, LxxConcordance>
     */
    public function hebrewLexicon(): BelongsTo
    {
        return $this->belongsTo(Lexicon::class, 'hebrew_strong', 'strong_id');
    }
}
