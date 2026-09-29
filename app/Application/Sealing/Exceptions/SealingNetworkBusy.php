<?php

namespace App\Application\Sealing\Exceptions;

use RuntimeException;

/**
 * It. 39: la cuenta selladora tiene otra transacción pendiente, y Stellar no
 * admite dos a la vez. Nada falló: el sello espera su turno y vuelve en unos
 * segundos, sin gastar un intento (US-021).
 */
class SealingNetworkBusy extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds, string $message)
    {
        parent::__construct($message);
    }
}
