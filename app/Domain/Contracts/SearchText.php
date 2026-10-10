<?php

namespace App\Domain\Contracts;

use Illuminate\Support\Str;

/**
 * It. 47a (US-016, V19): text as the veedor's search compares it — without
 * accents, in lowercase, with single spaces. "Vía" and "VIA" are "via". The
 * contract keeps its own normalized copy (search_text), and the keyword goes
 * through the same function.
 */
final class SearchText
{
    public static function of(?string ...$parts): string
    {
        $text = Str::lower(Str::ascii(implode(' ', array_filter($parts, fn (?string $part) => $part !== null && $part !== ''))));

        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
