<?php

namespace App\Domain\Reports;

use App\Domain\Geography\GeoPoint;
use App\Domain\Reports\Exceptions\ReportValidationException;

/**
 * US-008: once a worksite has an official location, a report has to be
 * taken within this radius of it. The radius is the one in force when
 * the report was captured (R-AUD-05), not when it arrived.
 */
final class Geofence
{
    public function __construct(
        public readonly GeoPoint $center,
        public readonly int $radiusMeters,
    ) {}

    /** @return float how far from the center $point is, in meters (it. 45f: the Bandeja shows it) */
    public function assertContains(GeoPoint $point): float
    {
        $distance = $this->center->distanceInMetersTo($point);

        if ($distance > $this->radiusMeters) {
            throw ReportValidationException::outsideGeofence($distance, $this->radiusMeters);
        }

        return $distance;
    }
}
