<?php

namespace App\Application\Reports;

use App\Domain\Configuration\Parameters;
use App\Domain\Contracts\Contract;
use App\Domain\Geography\GeoPoint;
use App\Domain\Organization\WatchedTerritories;
use App\Domain\Worksites\Worksite;

/**
 * US-019: las obras que el veedor tiene cerca — hasta 5 fichas ancladas a
 * menos de la geocerca (500 m, US-038-CFG), de la más cercana a la más
 * lejana, con Haversine en SQL (D10). Con las reglas de "Buscar Obra"
 * (US-016): un contrato del territorio (R-VC-04) que hoy admita reportes.
 * De cada ficha, uno de esos contratos: el reporte va a la ficha completa
 * de todos modos (R-INT-05).
 */
class NearbyWorksites
{
    public const LIMIT = 5;

    private const EARTH_RADIUS_METERS = 6_371_000;

    private const METERS_PER_DEGREE = 111_320;

    /** @return list<array{worksite: Worksite, distance_meters: int, contract: Contract}> */
    public function handle(GeoPoint $here): array
    {
        $radius = (int) Parameters::current('geofence_radius_meters');

        // Una caja alrededor, para no medir todas las fichas; dentro, la distancia exacta.
        $latitudeSpan = $radius / self::METERS_PER_DEGREE;
        $longitudeSpan = $radius / (self::METERS_PER_DEGREE * max(cos(deg2rad($here->latitude)), 0.01));

        $worksites = Worksite::query()
            ->whereNotNull('latitude')
            ->whereBetween('latitude', [$here->latitude - $latitudeSpan, $here->latitude + $latitudeSpan])
            ->whereBetween('longitude', [$here->longitude - $longitudeSpan, $here->longitude + $longitudeSpan])
            ->select('worksites.*')
            ->selectRaw(
                '2 * ? * asin(sqrt(power(sin(radians(latitude - ?) / 2), 2) + cos(radians(?)) * cos(radians(latitude)) * power(sin(radians(longitude - ?) / 2), 2))) as distance_meters',
                [self::EARTH_RADIUS_METERS, $here->latitude, $here->latitude, $here->longitude],
            )
            ->with('contracts')
            ->orderBy('distance_meters')
            ->get()
            ->filter(fn (Worksite $worksite) => $worksite->distance_meters <= $radius);

        $selectable = Contract::query()
            ->whereIn('secop_contract_id', $worksites->flatMap->contracts->pluck('secop_contract_id'))
            ->inTerritory(WatchedTerritories::ofActiveOrganizations(tenant()->getTenantKey()))
            ->reportableAt(now())
            ->orderBy('secop_contract_id')
            ->get()
            ->keyBy('secop_contract_id');

        $nearby = [];
        foreach ($worksites as $worksite) {
            $contract = $worksite->contracts->pluck('secop_contract_id')->sort()->map(fn (string $id) => $selectable->get($id))->filter()->first();
            if ($contract) {
                $nearby[] = ['worksite' => $worksite, 'distance_meters' => (int) round($worksite->distance_meters), 'contract' => $contract];
            }
            if (count($nearby) === self::LIMIT) {
                break;
            }
        }

        return $nearby;
    }
}
