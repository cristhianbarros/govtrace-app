<?php

namespace App\Application\Sealing\Exceptions;

use RuntimeException;

/** La cuenta patrocinadora no tiene XLM para pagar la comisión (US-020b). */
class SponsorOutOfFunds extends RuntimeException {}
