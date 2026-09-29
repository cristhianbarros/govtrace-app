<?php

namespace App\Domain\Sealing;

/**
 * US-021, R-INT-01: how many times, and how far apart, a seal is retried
 * when the Stellar network fails. Five attempts; between them 1 min, 5 min,
 * 15 min and 1 h. After the fifth, "Falla de Sellado".
 */
final class SealingRetryPolicy
{
    public const MAX_ATTEMPTS = 5;

    /** Seconds to wait after the 1st, 2nd, 3rd and 4th failed attempt. */
    public const DELAYS = [60, 300, 900, 3600];

    /** How long a transaction can go unconfirmed before it counts as a failed attempt. */
    public const CONFIRMATION_DEADLINE_SECONDS = 300;

    /** Seconds until the next attempt, or null when there is none left. */
    public static function delayAfter(int $failedAttempts): ?int
    {
        return $failedAttempts < self::MAX_ATTEMPTS ? self::DELAYS[$failedAttempts - 1] : null;
    }
}
