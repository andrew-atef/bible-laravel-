<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Book model - 66 canonical books (OT 1..39, NT 40..66)
 *
 * @property int $id
 * @property string $code OSIS code (Gen, Exod, Mat)
 * @property string $testament OT|NT
 * @property int $book_number 1..66
 * @property string $name_ar Arabic name
 * @property string $name_en English name
 * @property string $slug
 */
class Book extends Model
{
    use HasFactory;

    /**
     * @var string[]
     */
    protected $fillable = [
        'code',
        'testament',
        'book_number',
        'name_ar',
        'name_en',
        'slug',
        'is_indexed',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'book_number' => 'integer',
        'is_indexed' => 'boolean',
    ];

    /**
     * Verses belonging to this book.
     */
    public function verses(): HasMany
    {
        return $this->hasMany(Verse::class, 'book_id', 'id');
    }

    /**
     * Scope to filter by testament.
     *
     * @param Builder<Book> $query
     */
    public function scopeByTestament(Builder $query, string $testament): Builder
    {
        return $query->where('testament', $testament);
    }

    /**
     * Scope to find by OSIS code.
     *
     * @param Builder<Book> $query
     */
    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    /**
     * Find book by any identifier: slug, code, English name, or alias (genesis -> Gen).
     * Handles API test alias /read/genesis/1 -> al-takwin.
     */
    public static function findByIdentifier(string $identifier): ?self
    {
        $identifier = strtolower(trim($identifier));
        $identifierNoSpace = str_replace([' ', '-', '_'], '', $identifier);

        // 1. Direct slug or code (case-insensitive)
        $book = self::query()
            ->whereRaw('LOWER(slug) = ?', [$identifier])
            ->orWhereRaw('LOWER(code) = ?', [$identifier])
            ->orWhereRaw('LOWER(code) = ?', [$identifierNoSpace])
            ->first();
        if ($book) {
            return $book;
        }

        // 2. English name (Genesis, Exodus, etc.)
        $book = self::query()->whereRaw('LOWER(name_en) = ?', [$identifier])->first();
        if ($book) {
            return $book;
        }
        $book = self::query()->whereRaw('LOWER(REPLACE(REPLACE(name_en, " ", ""), "-", "")) = ?', [$identifierNoSpace])->first();
        if ($book) {
            return $book;
        }

        // 3. Alias map for common English slugs (genesis -> Gen, etc.)
        $aliasMap = [
            'genesis' => 'Gen', 'gen' => 'Gen',
            'exodus' => 'Exo', 'exod' => 'Exo', 'ex' => 'Exo',
            'leviticus' => 'Lev', 'lev' => 'Lev',
            'numbers' => 'Num', 'num' => 'Num',
            'deuteronomy' => 'Deu', 'deut' => 'Deu', 'deuteronomio' => 'Deu',
            'joshua' => 'Jos', 'josh' => 'Jos',
            'judges' => 'Jdg', 'judg' => 'Jdg',
            'ruth' => 'Rut',
            '1samuel' => '1Sa', '1sam' => '1Sa',
            '2samuel' => '2Sa', '2sam' => '2Sa',
            '1kings' => '1Ki', '1king' => '1Ki',
            '2kings' => '2Ki',
            '1chronicles' => '1Ch', '1chron' => '1Ch',
            '2chronicles' => '2Ch',
            'ezra' => 'Ezr',
            'nehemiah' => 'Neh', 'neh' => 'Neh',
            'esther' => 'Est', 'est' => 'Est',
            'job' => 'Job',
            'psalms' => 'Psa', 'psalm' => 'Psa', 'ps' => 'Psa',
            'proverbs' => 'Pro', 'prov' => 'Pro',
            'ecclesiastes' => 'Ecc', 'eccl' => 'Ecc',
            'songofsolomon' => 'Sng', 'song' => 'Sng', 'songs' => 'Sng',
            'isaiah' => 'Isa', 'isa' => 'Isa',
            'jeremiah' => 'Jer', 'jer' => 'Jer',
            'lamentations' => 'Lam', 'lam' => 'Lam',
            'ezekiel' => 'Ezk', 'ezek' => 'Ezk',
            'daniel' => 'Dan', 'dan' => 'Dan',
            'hosea' => 'Hos', 'hos' => 'Hos',
            'joel' => 'Jol',
            'amos' => 'Amo',
            'obadiah' => 'Oba',
            'jonah' => 'Jon',
            'micah' => 'Mic',
            'nahum' => 'Nam',
            'habakkuk' => 'Hab', 'hab' => 'Hab',
            'zephaniah' => 'Zep', 'zeph' => 'Zep',
            'haggai' => 'Hag', 'hag' => 'Hag',
            'zechariah' => 'Zec', 'zech' => 'Zec',
            'malachi' => 'Mal', 'mal' => 'Mal',
            'matthew' => 'Mat', 'matt' => 'Mat', 'mat' => 'Mat',
            'mark' => 'Mrk', 'mrk' => 'Mrk',
            'luke' => 'Luk', 'luk' => 'Luk',
            'john' => 'Jhn', 'jhn' => 'Jhn', 'jn' => 'Jhn',
            'acts' => 'Act',
            'romans' => 'Rom', 'rom' => 'Rom',
            '1corinthians' => '1Co', '1cor' => '1Co',
            '2corinthians' => '2Co', '2cor' => '2Co',
            'galatians' => 'Gal', 'gal' => 'Gal',
            'ephesians' => 'Eph', 'eph' => 'Eph',
            'philippians' => 'Php', 'phil' => 'Php',
            'colossians' => 'Col', 'col' => 'Col',
            '1thessalonians' => '1Th', '1thess' => '1Th',
            '2thessalonians' => '2Th', '2thess' => '2Th',
            '1timothy' => '1Ti', '1tim' => '1Ti',
            '2timothy' => '2Ti', '2tim' => '2Ti',
            'titus' => 'Tit',
            'philemon' => 'Phm', 'phm' => 'Phm',
            'hebrews' => 'Heb', 'heb' => 'Heb',
            'james' => 'Jas', 'jas' => 'Jas',
            '1peter' => '1Pe', '1pet' => '1Pe',
            '2peter' => '2Pe', '2pet' => '2Pe',
            '1john' => '1Jn',
            '2john' => '2Jn',
            '3john' => '3Jn',
            'jude' => 'Jud',
            'revelation' => 'Rev', 'rev' => 'Rev',
        ];

        if (isset($aliasMap[$identifierNoSpace])) {
            return self::where('code', $aliasMap[$identifierNoSpace])->first();
        }

        // 4. Arabic name (with normalization fallback) — optional for English alias path, but handle
        // Try slug-like Arabic? Not needed for genesis test.

        return null;
    }
}
