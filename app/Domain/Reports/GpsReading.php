<?php

namespace App\Domain\Reports;

use App\Domain\Geography\GeoPoint;
use App\Domain\Reports\Exceptions\ReportValidationException;

/**
 * The position the phone reported (R-GEO-01) — only good enough to
 * certify a report when its accuracy is 50 m or better (US-008).
 */
final class GpsReading
{
    private const MAX_ACCURACY_METERS = 50;

    private function __construct(
        public readonly GeoPoint $point,
        public readonly float $accuracyMeters,
    ) {}

    public static function fromDevice(?float $latitude, ?float $longitude, ?float $accuracyMeters): self
    {
        if ($latitude === null || $longitude === null) {
            throw ReportValidationException::locationRequired();
        }

        if (abs($latitude) > 90 || abs($longitude) > 180) {
            throw ReportValidationException::invalidLocation();
        }

        if ($accuracyMeters === null || $accuracyMeters < 0 || $accuracyMeters > self::MAX_ACCURACY_METERS) {
            throw ReportValidationException::gpsTooImprecise($accuracyMeters, self::MAX_ACCURACY_METERS);
        }

        return new self(new GeoPoint($latitude, $longitude), $accuracyMeters);
    }
}
