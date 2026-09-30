<?php

namespace App\Application\Publication;

use App\Domain\Contracts\Contract;
use App\Domain\Reports\Report;
use App\Domain\Reports\ReportClassification;
use App\Domain\Worksites\PinColor;
use App\Domain\Worksites\Worksite;
use Illuminate\Support\Collection;

/**
 * It. 40b: a worksite's condition in words — the color of its pin (US-027)
 * and why it has it, for the view of the worksite (US-029). It follows the
 * same rules as the pin (PinColor, as PublicMap does): the latest published
 * evidence, and a contract whose end date passed while SECOP still shows it
 * in execution. The worst one wins, and every reason that counts is named.
 */
class WorksiteCondition
{
    private const TIMEZONE = 'America/Bogota';

    private const LABELS = [
        'green' => 'Normal',
        'yellow' => 'Alerta',
        'red' => 'En riesgo',
    ];

    /**
     * @param  Collection<int, Contract>  $contracts  the worksite's contracts
     * @return array{color: string, label: string, reason: string}
     */
    public function of(Worksite $worksite, Collection $contracts): array
    {
        $latest = Report::query()
            ->onPublicMap()
            ->where('worksite_id', $worksite->id)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->first(['classification', 'captured_at']);
        $overdue = $contracts->contains(fn (Contract $contract) => $contract->isOverdueInExecution(today()));

        $color = PinColor::ofEvidence($latest?->classification)->worst($overdue ? PinColor::Red : PinColor::Green);

        return [
            'color' => $color->value,
            'label' => self::LABELS[$color->value],
            'reason' => $this->reason($latest, $overdue),
        ];
    }

    private function reason(?Report $latest, bool $overdue): string
    {
        $reasons = [];
        if ($overdue) {
            $reasons[] = 'La fecha de terminación del contrato ya pasó y SECOP II lo sigue mostrando en ejecución.';
        }
        if ($latest !== null && ($latest->classification !== ReportClassification::Progress || ! $overdue)) {
            $day = $latest->captured_at->setTimezone(self::TIMEZONE)->format('d/m/Y');
            $reasons[] = "El reporte publicado más reciente, del {$day}, es de ".mb_strtolower($latest->classification->value).'.';
        }

        return $reasons === []
            ? 'Ningún reporte publicado habla de retraso o abandono, y el contrato está dentro del plazo.'
            : implode(' ', $reasons);
    }
}
