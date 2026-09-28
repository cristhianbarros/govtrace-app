<?php

namespace App\Domain\Organization;

use App\Domain\Organization\Exceptions\OrganizationValidationException;

/**
 * A Colombian NIT with its check digit, validated against the DIAN's
 * official "MOD 11" algorithm (US-001, Completitud SEC — the user chose
 * "obligatorio y comprobado", not "sin dígito de verificación").
 * Immutable: build one with fromString(), there is no setter.
 */
final class Nit
{
    /** Right-to-left weights, DIAN Resolución 8 de 2000. */
    private const WEIGHTS = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];

    private function __construct(
        public readonly string $base,
        public readonly string $checkDigit,
    ) {}

    public static function fromString(string $value): self
    {
        if (! preg_match('/^(\d+)-(\d)$/', trim($value), $matches)) {
            throw OrganizationValidationException::invalidNit();
        }

        [, $base, $checkDigit] = $matches;

        if (self::computeCheckDigit($base) !== $checkDigit) {
            throw OrganizationValidationException::invalidNit();
        }

        return new self($base, $checkDigit);
    }

    public function value(): string
    {
        return "{$this->base}-{$this->checkDigit}";
    }

    private static function computeCheckDigit(string $base): string
    {
        $sum = 0;
        foreach (array_reverse(str_split($base)) as $position => $digit) {
            $sum += (int) $digit * (self::WEIGHTS[$position] ?? 0);
        }

        $remainder = $sum % 11;

        return (string) ($remainder <= 1 ? $remainder : 11 - $remainder);
    }
}
