<?php

namespace App\Domain\Worksites;

use App\Domain\Configuration\Parameters;
use App\Domain\Geography\Municipality;
use App\Domain\Geography\PlaceName;
use App\Domain\Reports\Report;

/**
 * It. 46f: why the first report of a worksite did not fix its location, in
 * words — for the veedor right after sending it, and for the Administrador
 * in the Bandeja. Built from what the server recorded on arrival, with the
 * distance in force when the report was captured (R-AUD-05).
 */
final class PendingLocation
{
    /** "se tomó a 42.3 km de Santa Marta, y para fijar una obra…", or null if it fixed the location (or had none to fix). */
    public static function reason(Report $report): ?string
    {
        return match ($report->anchor_withheld) {
            AnchorWithheld::FarFromMunicipality => sprintf(
                'se tomó a %s km de %s, y para fijar una obra hay que estar a menos de %s km de su municipio',
                number_format($report->distance_to_municipality_meters / 1000, 1, '.', ''),
                PlaceName::forDisplay(Municipality::query()->whereKey($report->reference_municipality_code)->value('name') ?? 'su municipio'),
                Parameters::valueAt('anchor_municipality_radius_km', $report->captured_at),
            ),
            AnchorWithheld::ImpreciseGps => sprintf(
                'la señal del GPS tenía una precisión de %d m, y para fijar una obra se necesitan %d m o menos',
                (int) round((float) $report->accuracy_meters),
                FirstTouch::MAX_ACCURACY_METERS,
            ),
            null => null,
        };
    }

    /** What the veedor reads after sending it. */
    public static function messageFor(Report $report): ?string
    {
        $reason = self::reason($report);
        if ($reason === null) {
            return null;
        }

        $subject = $report->anchor_withheld === AnchorWithheld::FarFromMunicipality ? 'su reporte ' : '';

        return "La ubicación de esta obra queda por confirmar: {$subject}{$reason}. La veeduría la revisará.";
    }
}
