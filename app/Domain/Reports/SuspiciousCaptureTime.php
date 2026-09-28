<?php

namespace App\Domain\Reports;

use Carbon\CarbonInterface;

/**
 * R-SEC-05: the phone's capture time is suspicious when it lies more than
 * 5 minutes in the future of the server's receipt, or further back than
 * the 7 days a report may wait offline. R-MON-02: it is only flagged,
 * never rejected — the Administrador de Organización decides when
 * reviewing it.
 */
final class SuspiciousCaptureTime
{
    public const OFFLINE_VALIDITY_DAYS = 7;

    /**
     * Network latency and the natural drift of a phone's clock — the same
     * idea as the leeway when validating a JWT. Small enough to open no
     * real window for backdating evidence.
     */
    public const CLOCK_SKEW_TOLERANCE_SECONDS = 300;

    public static function applies(CarbonInterface $capturedAt, CarbonInterface $receivedAt): bool
    {
        return $capturedAt->greaterThan($receivedAt->copy()->addSeconds(self::CLOCK_SKEW_TOLERANCE_SECONDS))
            || $capturedAt->lessThan($receivedAt->copy()->subDays(self::OFFLINE_VALIDITY_DAYS));
    }
}
