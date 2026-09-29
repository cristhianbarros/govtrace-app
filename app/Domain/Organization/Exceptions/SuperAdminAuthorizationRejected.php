<?php

namespace App\Domain\Organization\Exceptions;

use Carbon\CarbonInterface;
use DomainException;

/** Why an authorization to the Super Administrador was not granted or revoked (US-042-SEC). */
class SuperAdminAuthorizationRejected extends DomainException
{
    public static function alreadyInForce(CarbonInterface $until): self
    {
        $date = $until->copy()->timezone('America/Bogota')->format('d/m/Y');

        return new self("Ya hay una autorización vigente, hasta el {$date}. Revóquela antes de otorgar otra.");
    }

    public static function noneInForce(): self
    {
        return new self('No hay una autorización vigente que revocar.');
    }
}
