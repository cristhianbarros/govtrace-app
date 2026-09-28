<?php

namespace App\Application\Sealing\Exceptions;

use RuntimeException;

/** La red rechazó o no pudo procesar un sello por otro motivo (reintentos: US-021, it. 22). */
class SealingNetworkError extends RuntimeException {}
