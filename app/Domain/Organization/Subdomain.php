<?php

namespace App\Domain\Organization;

use App\Domain\Organization\Exceptions\OrganizationValidationException;
use Illuminate\Support\Str;

/**
 * An organization's subdomain — assigned once, at registration, by the
 * Super Administrator (R-SA-03). Format and reserved-word rules from
 * US-001; it. 51: proposed from the organization's name (slugOf) and editable before
 * registering; there is no historia anywhere that lets it change afterwards
 * (see App\Infrastructure\Tenancy\Domain for the immutability guard).
 */
final class Subdomain
{
    private const MIN_LENGTH = 3;

    /** El límite del DNS para una etiqueta (it. 51). */
    private const MAX_LENGTH = 63;

    private const RESERVED = ['api', 'admin', 'app', 'www', 'auth', 'assets'];

    private function __construct(public readonly string $value) {}

    /**
     * It. 51: lo que propone el nombre de la veeduría — Str::slug(): sin tildes ni eñe, en minúsculas,
     * con guiones donde había espacios o símbolos —, cortado a 63 caracteres sin dejar un guion al final.
     * Puede salir vacío (un nombre sin letras ni números): entonces no hay nada que proponer.
     */
    public static function slugOf(string $name): string
    {
        return rtrim(mb_substr(Str::slug($name), 0, self::MAX_LENGTH), '-');
    }

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
