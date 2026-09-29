<?php

namespace App\Application\Auth;

use App\Models\User;

/** The Super Administrador just created, and the password generated for them, if none was given. */
final class CreatedSuperAdministrator
{
    public function __construct(
        public readonly User $user,
        public readonly ?string $generatedPassword,
    ) {}
}
