<?php

namespace App\Domain\Shared;

use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * It. 46c (US-064-SEC, R-SEC-08): el identificador de un registro en las URL
 * y el API. Un ULID en minúsculas: 26 caracteres, ordenable por fecha, que no
 * dice cuántos registros hay ni deja recorrerlos en orden. Las llaves
 * numéricas siguen adentro: la referencia de la obra sellada en Stellar se
 * calcula con ellas (R-BLK-02).
 */
final class PublicId
{
    /** For route constraints: Crockford's base 32, in lower case. */
    public const PATTERN = '[0-9a-hjkmnp-tv-z]{26}';

    /** A new one; with $at, one that carries that date (for the records that already existed). */
    public static function generate(?DateTimeInterface $at = null): string
    {
        return strtolower((string) Str::ulid($at));
    }

    public static function isValid(mixed $value): bool
    {
        return is_string($value) && preg_match('/^'.self::PATTERN.'$/', $value) === 1;
    }
}
