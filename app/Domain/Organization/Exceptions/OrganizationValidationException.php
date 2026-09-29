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

    public static function invalidDisplayNameLength(): self
    {
        return new self('El nombre de fantasía debe tener entre 3 y 100 caracteres.');
    }

    public static function invalidAdministratorEmail(): self
    {
        return new self('El correo electrónico no tiene un formato válido.');
    }

    public static function duplicateAdministratorEmail(): self
    {
        return new self('El correo electrónico ya se encuentra registrado en el sistema.');
    }

    public static function duplicateObserverEmail(): self
    {
        return new self('Ya existe un usuario registrado o una invitación pendiente con este correo electrónico en la organización.');
    }

    public static function cannotRegisterFromTenantContext(): self
    {
        return new self('Solo el Super Administrador, desde el panel global, puede dar de alta una organización.');
    }

    public static function cannotEditLegalDataFromTenantContext(): self
    {
        return new self('Solo el Super Administrador, desde el panel global, puede editar el NIT o los datos legales de una organización.');
    }

    public static function cannotChangeStatusFromTenantContext(): self
    {
        return new self('Solo el Super Administrador, desde el panel global, puede suspender, reactivar o dar de baja una organización.');
    }

    public static function alreadySuspended(): self
    {
        return new self('La organización seleccionada ya se encuentra en estado suspendido.');
    }

    public static function alreadyActive(): self
    {
        return new self('La organización seleccionada ya se encuentra activa.');
    }

    /** US-003b: a decommissioned organization stays so — no second decommission, suspension or reactivation. */
    public static function alreadyDecommissioned(): self
    {
        return new self('La organización ya fue dada de baja: es definitivo.');
    }

    public static function decommissionNotConfirmed(): self
    {
        return new self('La primera confirmación venció o no es válida. Vuelva a solicitar la baja.');
    }

    public static function decommissionSubdomainMismatch(string $subdomain): self
    {
        return new self("Escriba el subdominio de la organización ({$subdomain}) para confirmar la baja.");
    }

    /** US-040-USR: there's no invitation to resend or revoke. */
    public static function noPendingInvitation(): self
    {
        return new self('El veedor no tiene una invitación pendiente.');
    }

    public static function observerAlreadyInactive(): self
    {
        return new self('El veedor ya está inactivo.');
    }

    public static function observerAlreadyActive(): self
    {
        return new self('El veedor ya está activo.');
    }

    public static function emptyTerritory(): self
    {
        return new self('Debe seleccionar al menos un departamento o municipio para delimitar el territorio de vigilancia.');
    }

    public static function unknownGeographyCode(): self
    {
        return new self('El código geográfico no pertenece a la tabla oficial de departamentos y municipios.');
    }
}
