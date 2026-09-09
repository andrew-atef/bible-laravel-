<?php

declare(strict_types=1);

namespace App\Models;

/**
 * WordToken alias for ArabicConcordance
 * Provides semantic naming for the lexical platform (token = single word alignment).
 *
 * This class exists for DX: `WordToken::byVerse('Gen.1.1')` reads more naturally
 * in the biblical computing domain while sharing the same table.
 */
class WordToken extends ArabicConcordance
{
    // Inherits everything from ArabicConcordance; table is arabic_concordance.
}
