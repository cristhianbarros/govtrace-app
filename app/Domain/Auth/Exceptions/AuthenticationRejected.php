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

    public static function accountDeactivated(): self
    {
        return new self('Su cuenta se encuentra desactivada. Comuníquese con el administrador de su organización.');
    }
}
