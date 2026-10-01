<?php

namespace App\Domain\Reports;

use App\Domain\Sealing\SealStatus;

/**
 * US-036: the evidences waiting for the Administrador's review — sealed and
 * still hidden. Counted for the "Bandeja" tab (it. 40c) and the daily
 * digest (US-060-MON, it. 43i). Runs inside the organization.
 */
final class PendingReview
{
    public static function count(): int
    {
        return Report::query()
            ->where('editorial_status', EditorialStatus::Hidden)
            ->whereHas('seal', fn ($seal) => $seal->where('status', SealStatus::Sealed))
            ->count();
    }
}
