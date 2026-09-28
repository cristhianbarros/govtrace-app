<?php

namespace App\Domain\Organization;

use App\Domain\Organization\Exceptions\OrganizationValidationException;

/**
 * An organization's subdomain — assigned once, at registration, by the
 * Super Administrator (R-SA-03). Format and reserved-word rules from
 * US-001; there is no historia anywhere that lets it change afterwards
 * (see App\Infrastructure\Tenancy\Domain for the immutability guard).
 */
final class Subdomain
{
    private const MIN_LENGTH = 3;

    private const RESERVED = ['api', 'admin', 'app', 'www', 'auth', 'assets'];

    private function __construct(public readonly string $value) {}

    public static function fromString(string $value): self
    {
        if (! preg_match('/^[a-z0-9-]+$/', $value) || mb_strlen($value) < self::MIN_LENGTH) {
            throw OrganizationValidationException::invalidSubdomainFormat();
        }

        if (in_array($value, self::RESERVED, true)) {
            throw OrganizationValidationException::reservedSubdomain();
        }

        return new self($value);
    }
}
