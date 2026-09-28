<?php

namespace App\Domain\Organization;

use App\Domain\Organization\Exceptions\OrganizationValidationException;

/**
 * The name the organization shows to its veedores and on its public map
 * (US-007) — "Ojo Ciudadano SMR". Not its legal name, which goes with the
 * NIT and only the Super Administrador changes (US-011).
 */
final class DisplayName
{
    private const MIN_LENGTH = 3;

    private const MAX_LENGTH = 100;

    private function __construct(public readonly string $value) {}

    public static function fromString(?string $value): self
    {
        $trimmed = trim((string) $value);
        $length = mb_strlen($trimmed);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw OrganizationValidationException::invalidDisplayNameLength();
        }

        return new self($trimmed);
    }
}
