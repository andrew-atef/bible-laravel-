<?php

declare(strict_types=1);

namespace App\Services\Biblical;

/**
 * ArabicScriptService — high-performance Arabic string normalization & highlighting
 *
 * Handles the Arabic-first UX core:
 *  - Strip Tashkeel (حركات) for search
 *  - Normalize Alef/Yaa/Taa-Marbuta orthographic variants
 *  - Safe HTML highlighting without breaking tags
 */
class ArabicScriptService
{
    /**
     * Normalize Arabic text for search indexing / lookup.
     *
     * Steps:
     *  1. Remove Tatweel (ـ U+0640) and Tashkeel (U+064B–U+065F, U+0670, U+06D6–U+06ED)
     *  2. Normalize Alef forms (أ إ آ ا) → ا (U+0627)
     *  3. Normalize Yaa forms (ى ئ) → ي (U+064A)
     *  4. Normalize Taa-Marbuta (ة) → ه (U+0647) for fuzzy matching
     *  5. Collapse whitespace, trim
     *
     * Deterministic & idempotent — safe to call repeatedly.
     */
    public function normalizeForSearch(string $text): string
    {
        if ($text === '') {
            return '';
        }

        // 1. Strip Tatweel
        $text = str_replace("\u{0640}", '', $text);

        // 1b. Strip Tashkeel / diacritics (Harakat + Shadda + Sukun + etc.)
        // U+064B..U+065F, U+0670, U+06D6..U+06ED covers 90% of Quran/Bible Tashkeel
        $text = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $text);

        // 2. Normalize Alef variants → bare Alef
        $text = (string) preg_replace('/[\x{0622}\x{0623}\x{0625}]/u', "\u{0627}", $text);

        // Handle Alef + Madda/Hamza edge: آ simplified above already

        // 3. Normalize Yaa variants → ي
        $text = str_replace(["\u{0649}", "\u{0626}"], "\u{064A}", $text);

        // 3b. Optional: normalize Waw Hamza ؤ → و (helps search recall)
        $text = str_replace("\u{0624}", "\u{0648}", $text);

        // 4. Normalize Taa-Marbuta → Haa (common SVD search fuzzy)
        $text = str_replace("\u{0629}", "\u{0647}", $text);

        // 4b. Normalize Lam-Alef ligatures ﻻ → لا (if any presentation forms leaked)
        $text = str_replace(["\u{FEFB}", "\u{FEFC}", "\u{FEF7}", "\u{FEF8}", "\u{FEF9}", "\u{FEFA}"], "\u{0644}\u{0627}", $text);

        // 5. Collapse whitespace, trim
        $text = (string) preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        return $text;
    }

    /**
     * Highlight a word inside a sentence with <mark> without breaking HTML.
     *
     * Safety guarantees:
     *  - Escapes HTML if sentence appears to be plain text; preserves tags if sentence already contains HTML.
     *  - Highlights only text nodes (never inside tag attributes).
     *  - Single replacement per occurrence, case/Tashkeel tolerant via normalization comparison.
     *  - Uses <mark> (semantic) but project CSS may style as .highlight; we emit <mark class="highlight">
     *
     * @param string $sentence Full Arabic sentence or HTML fragment (e.g. "فِي الْبَدْءِ خَلَقَ اللهُ")
     * @param string $word     Exact word to highlight (e.g. "فِي الْبَدْءِ")
     */
    public function highlightToken(string $sentence, string $word): string
    {
        if ($sentence === '' || $word === '') {
            return $this->escapeIfNeeded($sentence);
        }

        // If sentence contains HTML tags, operate per text-node to avoid breaking markup
        if ($this->containsHtml($sentence)) {
            return $this->highlightInHtml($sentence, $word);
        }

        // Plain text path: escape then highlight
        $escapedSentence = htmlspecialchars($sentence, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedWord = htmlspecialchars($word, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if ($escapedWord === '') {
            return $escapedSentence;
        }

        // Tashkeel-insensitive fallback: if exact not found, try normalized match
        // We do two passes: exact first, then normalized on original (preserve original diacritics in output)
        $highlighted = $this->wrapExactInsensitive($escapedSentence, $escapedWord);

        if ($highlighted === $escapedSentence) {
            // Try normalized comparison while preserving original forms in output
            $highlighted = $this->wrapNormalized($escapedSentence, $escapedWord);
        }

        return $highlighted;
    }

    /**
     * Wrap exact occurrence (Unicode-aware, case-insensitive for Arabic irrelevant but safe).
     */
    private function wrapExactInsensitive(string $sentence, string $word): string
    {
        $pattern = '/' . preg_quote($word, '/') . '/u';

        return (string) preg_replace(
            $pattern,
            '<mark class="highlight">$0</mark>',
            $sentence,
            1 // highlight first occurrence only to avoid over-marking; change to -1 for all
        );
    }

    /**
     * Normalized wrap: matches normalized forms while keeping original in output.
     * Supports multi-word phrases by sliding-window over word tokens.
     */
    private function wrapNormalized(string $sentence, string $word): string
    {
        $normalizedWord = $this->normalizeForSearch(html_entity_decode($word, ENT_QUOTES, 'UTF-8'));
        if ($normalizedWord === '') {
            return $sentence;
        }

        // Phrase tokens (normalized)
        $phraseTokens = preg_split('/\s+/u', $normalizedWord, -1, PREG_SPLIT_NO_EMPTY);
        if ($phraseTokens === false || $phraseTokens === []) {
            return $sentence;
        }
        $phraseLen = count($phraseTokens);

        // Tokenize sentence by spaces/punctuation but preserve delimiters
        $tokens = preg_split('/(\s+|[،؛:\-—\(\)\[\]{}"\'«»])/u', $sentence, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($tokens === false) {
            return $sentence;
        }

        // Map word-token indexes (non-delimiter)
        $wordIndexes = [];
        foreach ($tokens as $idx => $tok) {
            if (trim($tok) === '' || preg_match('/^[\s،؛:\-—\(\)\[\]{}"\'«»]+$/u', $tok)) {
                continue;
            }
            $wordIndexes[] = $idx;
        }

        if (count($wordIndexes) < $phraseLen) {
            return $sentence;
        }

        // Sliding window search
        $foundStart = null;
        $foundEnd = null;
        for ($i = 0; $i <= count($wordIndexes) - $phraseLen; $i++) {
            $match = true;
            for ($k = 0; $k < $phraseLen; $k++) {
                $tokIdx = $wordIndexes[$i + $k];
                $decoded = html_entity_decode($tokens[$tokIdx], ENT_QUOTES, 'UTF-8');
                $normTok = $this->normalizeForSearch($decoded);
                $normTokTrim = trim($normTok, " \t\n\r\0\x0B.,;:'\"()[]{}");
                if ($normTokTrim !== $phraseTokens[$k]) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $foundStart = $wordIndexes[$i];
                $foundEnd = $wordIndexes[$i + $phraseLen - 1];
                break;
            }
        }

        if ($foundStart === null) {
            return $sentence;
        }

        // Wrap the slice from foundStart to foundEnd inclusive (preserving spaces/punct between)
        $slice = array_slice($tokens, $foundStart, $foundEnd - $foundStart + 1);
        $wrappedSlice = '<mark class="highlight">' . implode('', $slice) . '</mark>';

        $newTokens = array_merge(
            array_slice($tokens, 0, $foundStart),
            [$wrappedSlice],
            array_slice($tokens, $foundEnd + 1)
        );

        return implode('', $newTokens);
    }

    /**
     * Highlight inside an HTML fragment by splitting on tags.
     */
    private function highlightInHtml(string $html, string $word): string
    {
        // Split html into tags vs text nodes
        $parts = preg_split('/(<[^>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return $html;
        }

        $escapedWord = htmlspecialchars($word, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $normalizedWord = $this->normalizeForSearch($word);

        $highlightedOnce = false;

        foreach ($parts as &$part) {
            if ($part === '' || $part[0] === '<') {
                continue; // tag, skip
            }
            if ($highlightedOnce) {
                continue;
            }

            // Part is text node — attempt highlight
            // Try exact first
            $candidate = $this->wrapExactInsensitive($part, $escapedWord);
            if ($candidate !== $part) {
                $part = $candidate;
                $highlightedOnce = true;
                continue;
            }

            // Try normalized
            $origPart = $part;
            $wrapped = $this->wrapNormalized($part, $escapedWord);
            if ($wrapped !== $origPart) {
                $part = $wrapped;
                $highlightedOnce = true;
            }
        }
        unset($part);

        return implode('', $parts);
    }

    private function containsHtml(string $text): bool
    {
        if (! str_contains($text, '<') || ! str_contains($text, '>')) {
            return false;
        }

        // Whitelist safe tags; treat <script>, <img onerror>, etc. as plain text to escape
        // Only consider it HTML if it contains known safe formatting tags
        if (preg_match('/<\s*(p|b|i|strong|em|mark|span|div|ul|ol|li|h[1-6]|br|a|small|sup|sub)\b[^>]*>/i', $text)) {
            return true;
        }

        // If it contains script/style/iframe/img with event handlers, do NOT treat as safe HTML
        if (preg_match('/<\s*(script|style|iframe|img|svg|object|embed)\b/i', $text)) {
            return false;
        }

        return false;
    }

    private function escapeIfNeeded(string $text): string
    {
        if ($this->containsHtml($text)) {
            return $text; // assume already safe HTML fragment
        }

        // If text contains any < > but not whitelisted, escape it
        if (str_contains($text, '<') || str_contains($text, '>')) {
            return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
