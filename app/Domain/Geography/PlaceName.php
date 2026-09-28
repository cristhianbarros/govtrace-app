<?php

namespace App\Domain\Geography;

/**
 * DIVIPOLA writes names in capitals ("BOGOTÁ, D.C."). On screen they read
 * as a person would write them ("Bogotá, D.C.", "Archipiélago de San
 * Andrés…"); the stored name stays the official one.
 */
final class PlaceName
{
    private const LOWERCASE_WORDS = ['de', 'del', 'la', 'las', 'los', 'el', 'y', 'e'];

    public static function forDisplay(string $official): string
    {
        $words = explode(' ', mb_strtolower(trim($official)));

        foreach ($words as $position => $word) {
            $words[$position] = match (true) {
                $position > 0 && in_array($word, self::LOWERCASE_WORDS, true) => $word,
                str_contains($word, '.') => mb_strtoupper($word), // D.C.
                default => mb_convert_case($word, MB_CASE_TITLE),
            };
        }

        return implode(' ', $words);
    }
}
