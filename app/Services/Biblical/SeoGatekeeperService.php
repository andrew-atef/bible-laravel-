<?php

declare(strict_types=1);

namespace App\Services\Biblical;

use App\Models\Book;
use App\Models\Lexicon;
use App\Models\Verse;
use Illuminate\Database\Eloquent\Model;

/**
 * SeoGatekeeperService — Staged Indexation Engine
 *
 * Prevents Scaled Content Abuse by gating Googlebot via
 * dynamic <meta name="robots"> and sitemap segmentation.
 * Humans browse everything; bots only index whitelisted ~40 pages in Month 1.
 */
class SeoGatekeeperService
{
    /**
     * Determine if an entity is indexable.
     *
     * @param Model|string|null $entity Book|Verse|Lexicon|osis string|chapter array|string homepage
     */
    public function isIndexable(Model|string|null $entity): bool
    {
        if ($entity === null) {
            return false;
        }

        // Homepage & static strings — always indexable in Month 1 pilot
        if (is_string($entity)) {
            $trimmed = trim($entity);
            if ($trimmed === '' || $trimmed === '/' || $trimmed === 'home' || $trimmed === 'homepage') {
                return true;
            }
            // If string looks like OSIS (Gen.1.1), treat as Verse
            if (preg_match('/^[1-3]?[A-Za-z]{2,3}\.\d+\.\d+$/', $trimmed)) {
                $verse = Verse::where('osis_id', $trimmed)->first(['is_indexed', 'book_id']);
                if ($verse) {
                    if ((bool) $verse->is_indexed) {
                        return true;
                    }
                    // Inherit from book
                    $book = Book::where('id', $verse->book_id)->first(['is_indexed']);
                    return $book ? (bool) $book->is_indexed : false;
                }
                return false;
            }
            // If string is a Strong ID, treat as Lexicon
            if (preg_match('/^[HG]\d+$/i', $trimmed)) {
                $lex = Lexicon::where('strong_id', strtoupper($trimmed))->first(['is_indexed']);
                return $lex ? (bool) $lex->is_indexed : false;
            }
            // Unknown string — not indexable
            return false;
        }

        if ($entity instanceof Book) {
            return (bool) ($entity->is_indexed ?? false);
        }

        if ($entity instanceof Verse) {
            if ((bool) ($entity->is_indexed ?? false)) {
                return true;
            }
            // Inherit from book
            $book = $entity->relationLoaded('book') ? $entity->book : $entity->book()->first(['is_indexed']);
            return $book ? (bool) $book->is_indexed : false;
        }

        if ($entity instanceof Lexicon) {
            return (bool) ($entity->is_indexed ?? false);
        }

        // Generic Model with is_indexed attribute
        if (isset($entity->is_indexed)) {
            return (bool) $entity->is_indexed;
        }

        return false;
    }

    /**
     * Render robots meta tag string (safe HTML, no escaping needed when using {!! !!}).
     */
    public function renderRobotsMeta(Model|string|null $entity): string
    {
        if ($this->isIndexable($entity)) {
            return '<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large">';
        }

        return '<meta name="robots" content="noindex, follow">';
    }

    /**
     * Helper: check if a chapter (book + chapter number) is indexable.
     * A chapter is indexable if any verse in that chapter is indexed OR book is indexed.
     */
    public function isChapterIndexable(Book $book, int $chapter): bool
    {
        if ((bool) ($book->is_indexed ?? false)) {
            return true;
        }

        return Verse::where('book_id', $book->id)
            ->where('chapter', $chapter)
            ->where('is_indexed', true)
            ->exists();
    }
}
