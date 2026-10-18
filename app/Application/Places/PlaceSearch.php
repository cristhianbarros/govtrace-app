<?php

namespace App\Application\Places;

use App\Application\CitizenReports\ReportedWork;
use App\Domain\Configuration\Parameters;
use App\Domain\Geography\Department;
use App\Domain\Geography\GeoPoint;
use App\Domain\Geography\Municipality;
use App\Domain\Geography\PlaceName;
use App\Infrastructure\Geocoding\NominatimClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;

/**
 * It. 68 (US-059-LEG, US-035): the places OpenStreetMap finds for what a
 * citizen, or the Administrador, wrote about where a work is. Only that text
 * leaves GovTrace, with the municipality and department of the contract, and
 * only places around the municipality come back: as far from its seat as a
 * proposed place may be (R-GEO-01). A contract without municipality is
 * searched in its department.
 *
 * Nominatim's policy: each search is kept 30 days, and the veeduría asks once
 * a second at most (the cache carries its prefix: it is per veeduría).
 *
 * Inside the organization.
 */
final class PlaceSearch
{
    private const LIMITER = 'nominatim';

    private const KEPT_DAYS = 30;

    private const KM_PER_DEGREE = 111;

    public function __construct(private readonly NominatimClient $nominatim) {}

    /** @return list<array{label: string, latitude: float, longitude: float}> */
    public function find(string $text, ReportedWork $work): array
    {
        $contract = $work->contract;
        $municipality = $contract?->municipality_code !== null ? Municipality::query()->find($contract->municipality_code) : null;
        $department = $contract?->department_code !== null ? Department::query()->find($contract->department_code) : null;
        $query = implode(', ', array_filter([
            trim((string) preg_replace('/\s+/u', ' ', $text)),
            $municipality !== null ? PlaceName::forDisplay($municipality->name) : null,
            $department !== null ? PlaceName::forDisplay($department->name) : null,
        ]));

        $key = 'places:'.sha1(mb_strtolower($query));
        $kept = Cache::get($key);
        if (is_array($kept)) {
            return $kept;
        }

        $places = $this->ask($query, $this->around($municipality?->seat()));
        Cache::put($key, $places, now()->addDays(self::KEPT_DAYS));

        return $places;
    }

    /** @return list<array{label: string, latitude: float, longitude: float}> */
    private function ask(string $query, ?array $viewbox): array
    {
        if (RateLimiter::tooManyAttempts(self::LIMITER, 1)) {
            Sleep::for(max(1, RateLimiter::availableIn(self::LIMITER)))->seconds();
        }
        RateLimiter::hit(self::LIMITER, 1);

        try {
            $rows = $this->nominatim->search($query, $viewbox);
        } catch (ConnectionException|RequestException) {
            throw new PlaceSearchUnavailable;
        }

        $places = array_map(fn (array $row) => isset($row['lat'], $row['lon'], $row['display_name']) ? [
            'label' => (string) preg_replace('/, Colombia$/u', '', (string) $row['display_name']),
            'latitude' => (float) $row['lat'],
            'longitude' => (float) $row['lon'],
        ] : null, array_filter($rows, is_array(...)));

        return array_slice(array_values(array_filter($places)), 0, NominatimClient::RESULTS);
    }

    /** @return array{0: float, 1: float, 2: float, 3: float}|null west, north, east and south, around the seat */
    private function around(?GeoPoint $seat): ?array
    {
        if ($seat === null) {
            return null;
        }
        $degrees = (int) Parameters::current('anchor_municipality_radius_km') / self::KM_PER_DEGREE;

        return array_map(fn (float $value) => round($value, 6), [
            $seat->longitude - $degrees, $seat->latitude + $degrees, $seat->longitude + $degrees, $seat->latitude - $degrees,
        ]);
    }
}
