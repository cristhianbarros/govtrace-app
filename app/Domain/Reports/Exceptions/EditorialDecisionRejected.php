<?php

namespace App\Domain\Reports\Exceptions;

use DomainException;

/**
 * An editorial decision the evidence doesn't allow (US-036, US-037). With a
 * $field, the input is what's wrong (a missing reason); without one, it's
 * the evidence's current status.
 */
class EditorialDecisionRejected extends DomainException
{
    private function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    public static function reasonRequired(string $message): self
    {
        return new self($message, 'reason');
    }

    public static function becauseOfStatus(string $message): self
    {
        return new self($message);
    }
}
