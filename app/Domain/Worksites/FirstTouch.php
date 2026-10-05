<?php

namespace App\Domain\Worksites;

use App\Domain\Geography\Municipality;
use App\Domain\Reports\GpsReading;

/**
 * First-Touch Anchoring with its guards (R-GEO-01, amended in it. 46f): the
 * first report of a worksite without location fixes it only when it was
 * taken near the seat of the contract's municipality and with a good GPS
 * signal. Otherwise the report is received all the same, and the location
 * is left for the Administrador to confirm from the Bandeja.
 */
final class FirstTouch
{
    /** Enough to send a report is 50 m (GpsReading); to fix where a worksite is, it takes 20 m. */
    public const MAX_ACCURACY_METERS = 20;

    private function __construct(
        public readonly ?AnchorWithheld $withheld,
        public readonly ?Municipality $seat,
        public readonly ?int $distanceToSeatMeters,
    ) {}

    /** $seat is null when there is nothing to measure against: then only the signal counts. */
    public static function judge(GpsReading $reading, ?Municipality $seat, int $maxDistanceToSeatMeters): self
    {
        $distance = $seat?->seat() !== null ? (int) round($seat->seat()->distanceInMetersTo($reading->point)) : null;

        $withheld = match (true) {
            $distance !== null && $distance > $maxDistanceToSeatMeters => AnchorWithheld::FarFromMunicipality,
            $reading->accuracyMeters > self::MAX_ACCURACY_METERS => AnchorWithheld::ImpreciseGps,
            default => null,
        };

        return new self($withheld, $seat, $distance);
    }

    public function fixesLocation(): bool
    {
        return $this->withheld === null;
    }
}
