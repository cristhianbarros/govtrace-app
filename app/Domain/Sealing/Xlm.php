<?php

namespace App\Domain\Sealing;

/**
 * Cantidades de XLM, exactas: la red las cuenta en stroops, enteros
 * (1 XLM = 10.000.000 stroops), así que aquí no pasan por floats.
 */
final class Xlm
{
    public const STROOPS = 10_000_000;

    /** "49.9" → 499000000. Up to 7 decimals, as the threshold parameter accepts (US-038-CFG). */
    public static function toStroops(string $xlm): int
    {
        [$whole, $fraction] = array_pad(explode('.', trim($xlm), 2), 2, '');

        return (int) $whole * self::STROOPS + (int) str_pad(substr($fraction, 0, 7), 7, '0');
    }

    /** 499000000 → "49.9": for data, with no trailing zeros. */
    public static function fromStroops(int $stroops): string
    {
        $fraction = rtrim(str_pad((string) ($stroops % self::STROOPS), 7, '0', STR_PAD_LEFT), '0');

        return intdiv($stroops, self::STROOPS).($fraction === '' ? '' : ".{$fraction}");
    }

    /** 12345678901 → "1.234,5678901": as an amount reads in Colombia, for a message. */
    public static function display(int $stroops): string
    {
        [$whole, $fraction] = array_pad(explode('.', self::fromStroops($stroops)), 2, null);

        return number_format((int) $whole, 0, ',', '.').($fraction === null ? '' : ",{$fraction}");
    }
}
