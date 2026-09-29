<?php

namespace App\Domain\Audit\Exceptions;

use DomainException;

/**
 * R-MNT-03: the audit log is kept forever. An entry is never edited nor
 * deleted; a mistake is corrected with a new entry, next to the old one.
 */
class AuditLogIsAppendOnly extends DomainException
{
    public static function make(): self
    {
        return new self('El log de auditoría no se edita ni se borra: se conserva para siempre.');
    }
}
