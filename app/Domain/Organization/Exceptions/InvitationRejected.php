<?php

namespace App\Domain\Organization\Exceptions;

use DomainException;

class InvitationRejected extends DomainException
{
    public static function expiredOrInvalid(): self
    {
        return new self('El enlace de invitación ha expirado o no es válido. Solicite una nueva invitación al administrador.');
    }
}
