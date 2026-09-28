<?php

namespace App\Domain\Organization\Exceptions;

use DomainException;

/**
 * Carries the exact Spanish message the user confirmed for each rule
 * during Criterios (specs/criterios/US-001.yaml) — these are what
 * features/US-001.feature asserts on, verbatim.
 */
class OrganizationValidationException extends DomainException
{
    public static function invalidNit(): self
    {
        return new self('El NIT ingresado no es válido o el dígito de verificación no coincide con el algoritmo de la DIAN.');
    }

    public static function duplicateNit(): self
    {
        return new self('Ya existe una organización registrada con el NIT ingresado.');
    }

    public static function invalidSubdomainFormat(): self
    {
        return new self('El subdominio solo puede contener letras minúsculas, números y guiones, sin espacios ni caracteres especiales.');
    }

    public static function reservedSubdomain(): self
    {
        return new self('El subdominio utiliza una palabra reservada del sistema y no puede ser utilizado.');
    }

    public static function duplicateSubdomain(): self
    {
        return new self('El subdominio especificado ya no está disponible. Por favor elija otro.');
    }

    public static function invalidNameLength(): self
    {
        return new self('El nombre de la organización debe tener entre 3 y 150 caracteres.');
    }
}
