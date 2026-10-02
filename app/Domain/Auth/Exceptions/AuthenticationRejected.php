<?php

namespace App\Domain\Auth\Exceptions;

use App\Domain\Organization\OrganizationStatus;
use DomainException;

/**
 * Carries the exact Spanish message confirmed in Criterios (US-031),
 * verbatim in features/US-031.feature.
 */
class AuthenticationRejected extends DomainException
{
    public static function invalidCredentials(): self
    {
        return new self('Credenciales incorrectas. Verifique su correo electrónico y contraseña.');
    }

    public static function tooManyAttempts(): self
    {
        return new self('Demasiados intentos fallidos. Tu cuenta ha sido bloqueada temporalmente durante 15 minutos.');
    }

    /** US-003a: at login, and to anyone still signed in or syncing from the app. */
    public static function organizationSuspended(): self
    {
        return new self('La organización veedora ha sido temporalmente suspendida. Contacte a soporte');
    }

    /** US-003b: the same, once the organization was decommissioned — for good. */
    public static function organizationDecommissioned(): self
    {
        return new self('La organización veedora fue dada de baja. Sus usuarios ya no tienen acceso.');
    }

    /** Why nobody of an organization in this status gets in; null if it's active. */
    public static function forOrganization(OrganizationStatus $status): ?self
    {
        return match ($status) {
            OrganizationStatus::Suspended => self::organizationSuspended(),
            OrganizationStatus::Decommissioned => self::organizationDecommissioned(),
            OrganizationStatus::Active => null,
        };
    }

    /** US-006: the session of a veedor who was deactivated while signed in (the app shows it when syncing). */
    public static function deactivatedWhileSignedIn(): self
    {
        return new self('Su cuenta ha sido desactivada. No es posible sincronizar nuevos reportes.');
    }

    public static function accountDeactivated(): self
    {
        return new self('Su cuenta se encuentra desactivada. Comuníquese con el administrador de su organización.');
    }

    /** It. 46a (US-063-USR): a Super Administrador answers to the other Super Administradores, not to an organization. */
    public static function superAdministratorDeactivated(): self
    {
        return new self('Su cuenta se encuentra desactivada. Comuníquese con otro Super Administrador de GovTrace.');
    }

    /** It. 46a: the open session of a Super Administrador who was deactivated, on its next request. */
    public static function superAdministratorDeactivatedWhileSignedIn(): self
    {
        return new self('Su cuenta de Super Administrador fue desactivada.');
    }
}
