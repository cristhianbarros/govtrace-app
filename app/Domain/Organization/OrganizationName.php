<?php

namespace App\Domain\Organization;

use App\Domain\Organization\Exceptions\OrganizationValidationException;

/**
 * The organization's legal/display name — obligatorio, 3 a 150 caracteres
 * (US-001).
 */
final class OrganizationName
{
    private const MIN_LENGTH = 3;

    private const MAX_LENGTH = 150;

    private function __construct(public readonly string $value) {}

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw OrganizationValidationException::invalidNameLength();
        }

        return new self($trimmed);
    }
}
