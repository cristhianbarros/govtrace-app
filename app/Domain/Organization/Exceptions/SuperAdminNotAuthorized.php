<?php

namespace App\Domain\Organization\Exceptions;

use DomainException;

/** R-SA-02: the Super Administrador acted in an organization that has not authorized it (US-042-SEC). */
class SuperAdminNotAuthorized extends DomainException
{
    public function __construct()
    {
        parent::__construct('No cuenta con una autorización activa de la organización para realizar esta acción.');
    }
}
