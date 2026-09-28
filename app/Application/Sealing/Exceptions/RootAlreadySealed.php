<?php

namespace App\Application\Sealing\Exceptions;

use RuntimeException;

/** "Hash ya registrado": el contrato rechazó la raíz con Error(Contract, #1) (US-020a). */
class RootAlreadySealed extends RuntimeException
{
    public function __construct(public readonly string $merkleRoot)
    {
        parent::__construct("Hash ya registrado: {$merkleRoot}");
    }
}
