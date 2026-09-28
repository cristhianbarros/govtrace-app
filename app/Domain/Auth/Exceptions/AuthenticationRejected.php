<?php

namespace App\Domain\Auth\Exceptions;

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

    /** US-006: the session of a veedor who was deactivated while signed in (the app shows it when syncing). */
    public static function deactivatedWhileSignedIn(): self
    {
        return new self('Su cuenta ha sido desactivada. No es posible sincronizar nuevos reportes.');
    }

    public static function accountDeactivated(): self
    {
        return new self('Su cuenta se encuentra desactivada. Comuníquese con el administrador de su organización.');
    }
}
