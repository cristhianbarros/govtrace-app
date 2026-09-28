<?php

namespace App\Domain\Reports;

use Carbon\CarbonInterface;

/**
 * R-SEC-05: the phone's capture time is suspicious when it lies in the
 * future of the server's receipt, or further back than the 7 days a
 * report may wait offline. R-MON-02: it is only flagged, never rejected —
 * the Administrador de Organización decides when reviewing it.
 */
final class SuspiciousCaptureTime
{
    public const OFFLINE_VALIDITY_DAYS = 7;

    public static function applies(CarbonInterface $capturedAt, CarbonInterface $receivedAt): bool
    {
        return $capturedAt->greaterThan($receivedAt)
            || $capturedAt->lessThan($receivedAt->copy()->subDays(self::OFFLINE_VALIDITY_DAYS));
    }
}
