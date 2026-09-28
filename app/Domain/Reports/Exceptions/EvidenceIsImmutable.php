<?php

namespace App\Domain\Reports\Exceptions;

use DomainException;

/**
 * R-TA-02: an evidence — the report, its files and its seal — is never
 * deleted nor altered, not even by the Administrador. Withdrawing it is
 * logical: it only changes its editorial status (US-037).
 */
class EvidenceIsImmutable extends DomainException
{
    public static function cannotDelete(): self
    {
        return new self('Una evidencia no se borra: se rechaza o se retira, y queda el rastro (R-TA-02).');
    }

    /** @param  list<string>  $fields */
    public static function cannotAlter(array $fields): self
    {
        return new self('Una evidencia no se altera (R-TA-02): '.implode(', ', $fields).'.');
    }
}
