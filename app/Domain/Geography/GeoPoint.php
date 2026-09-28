<?php

namespace App\Domain\Geography;

use InvalidArgumentException;

/**
 * A WGS84 coordinate — what a phone's GPS gives and what a worksite's
 * official location is (R-GEO-01).
 */
final class GeoPoint
{
    /** Mean Earth radius for the haversine distance, in meters. */
    private const EARTH_RADIUS_METERS = 6_371_000;

    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
    ) {
        if (abs($latitude) > 90 || abs($longitude) > 180) {
            throw new InvalidArgumentException("Coordenadas fuera de rango: {$latitude}, {$longitude}.");
        }
    }

    /** Great-circle (haversine) distance. */
    public function distanceInMetersTo(self $other): float
    {
        $fromLatitude = deg2rad($this->latitude);
        $toLatitude = deg2rad($other->latitude);
        $deltaLatitude = $toLatitude - $fromLatitude;
        $deltaLongitude = deg2rad($other->longitude - $this->longitude);

        $a = sin($deltaLatitude / 2) ** 2 + cos($fromLatitude) * cos($toLatitude) * sin($deltaLongitude / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * asin(sqrt($a));
    }
}
