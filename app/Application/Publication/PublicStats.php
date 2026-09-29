<?php

namespace App\Application\Publication;

use App\Domain\Contracts\Contract;
use App\Domain\Reports\Report;
use App\Domain\Worksites\PinColor;
use App\Domain\Worksites\WorksiteContract;

/**
 * US-051-RPT: las estadísticas públicas del territorio, sin sesión
 * (R-VER-02). Solo cuenta lo publicado:
 * - obras en riesgo: los pines rojos del mapa (US-027);
 * - evidencias publicadas por mes de captura, en la hora de Colombia;
 * - contratos anulados en SECOP cuya obra tiene evidencias publicadas.
 */
final class PublicStats
{
    private const TIMEZONE = 'America/Bogota';

    /** @return array{worksites_at_risk: int, published_by_month: list<array{month: string, total: int}>, cancelled_contracts_with_evidence: int} */
    public function handle(): array
    {
        $atRisk = collect((new PublicMap)->pins())->where('color_pin', PinColor::Red->value)->count();

        $byMonth = Report::query()
            ->onPublicMap()
            ->selectRaw("to_char((captured_at at time zone 'UTC') at time zone ?, 'YYYY-MM') as month, count(*) as total", [self::TIMEZONE])
            ->groupBy('month')
            ->orderBy('month')
            ->toBase()
            ->get()
            ->map(fn (object $row) => ['month' => $row->month, 'total' => (int) $row->total])
            ->all();

        $withEvidence = WorksiteContract::query()
            ->whereIn('worksite_id', Report::query()->onPublicMap()->select('worksite_id'))
            ->distinct()
            ->pluck('secop_contract_id');

        return [
            'worksites_at_risk' => $atRisk,
            'published_by_month' => $byMonth,
            'cancelled_contracts_with_evidence' => Contract::query()->whereIn('secop_contract_id', $withEvidence)->where('status', 'cancelled')->count(),
        ];
    }
}
