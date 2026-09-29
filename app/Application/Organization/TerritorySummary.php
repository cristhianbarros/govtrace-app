<?php

namespace App\Application\Organization;

use App\Application\Publication\PublicMap;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User;
use App\Domain\Reports\EditorialStatus;
use App\Domain\Reports\Report;
use App\Domain\Reports\ReportClassification;
use App\Domain\Worksites\PinColor;

/**
 * US-049-RPT: el estado de la veeduría de un vistazo, para su
 * Administrador — solo con datos de su organización, que viven en su base:
 * - obras por color, las mismas del mapa público (US-027);
 * - evidencias por clasificación y por mes, en la hora de Colombia. Todas
 *   las recibidas, publicadas o no, salvo las rechazadas: no eran de la obra;
 * - veedores activos: ni desactivados ni con la invitación pendiente.
 */
class TerritorySummary
{
    private const TIMEZONE = 'America/Bogota';

    /** @return array<string, mixed> */
    public function handle(): array
    {
        $classifications = array_map(fn (ReportClassification $classification) => $classification->value, ReportClassification::cases());
        $none = array_fill_keys($classifications, 0);

        $colors = array_fill_keys(array_map(fn (PinColor $color) => $color->value, PinColor::cases()), 0);
        foreach ((new PublicMap)->pins() as $pin) {
            $colors[$pin['color_pin']]++;
        }

        $received = Report::query()->where('editorial_status', '!=', EditorialStatus::Rejected);

        $byClassification = (clone $received)
            ->selectRaw('classification, count(*) as total')
            ->groupBy('classification')
            ->pluck('total', 'classification')
            ->all();

        $byMonth = [];
        $monthly = (clone $received)
            ->selectRaw("to_char((captured_at at time zone 'UTC') at time zone ?, 'YYYY-MM') as month, classification, count(*) as total", [self::TIMEZONE])
            ->groupBy('month', 'classification')
            ->orderBy('month')
            ->toBase()
            ->get();
        foreach ($monthly as $row) {
            $byMonth[$row->month] ??= ['month' => $row->month, ...$none];
            $byMonth[$row->month][$row->classification] = (int) $row->total;
        }

        return [
            'worksites_by_color' => $colors,
            'evidences_by_classification' => array_map('intval', array_merge($none, array_intersect_key($byClassification, $none))),
            'evidences_by_month' => array_values($byMonth),
            'active_observers' => User::role(Roles::Observer->value, 'tenant')
                ->where('is_active', true)
                ->whereNull('invitation_token_hash')
                ->count(),
        ];
    }
}
