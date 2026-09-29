<?php

namespace App\Application\Publication;

use App\Domain\Contracts\Contract;
use App\Domain\Reports\Report;
use App\Domain\Worksites\PinColor;
use App\Domain\Worksites\Worksite;
use App\Domain\Worksites\WorksiteContract;

/**
 * US-027: the pins of an organization's public map (R-MAP-01) — its
 * anchored worksites, and of each only what the first load needs,
 * [{id, lat, lng, color_pin}] (R-MAP-02). The rest is asked for on a
 * click (PublicWorksiteView). Three queries, whatever the number of pins.
 *
 * Every anchored worksite has its pin, whatever its contracts' status:
 * its published evidence stays public (an annulled contract, US-017, or
 * one closed long ago — the white elephants are why the map exists).
 */
class PublicMap
{
    /** @return list<array{id: int, lat: float, lng: float, color_pin: string}> */
    public function pins(): array
    {
        $worksites = Worksite::query()->whereNotNull('latitude')->with('contracts')->orderBy('id')->get();

        $contracts = Contract::query()
            ->whereIn('secop_contract_id', $worksites->flatMap->contracts->pluck('secop_contract_id'))
            ->get(['secop_contract_id', 'status', 'end_date'])
            ->keyBy('secop_contract_id');

        // La evidencia publicada más reciente de cada ficha (DISTINCT ON): la última verdad conocida.
        $latestEvidence = Report::query()
            ->onPublicMap()
            ->whereIn('worksite_id', $worksites->modelKeys())
            ->distinct('worksite_id')
            ->orderBy('worksite_id')
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->get(['worksite_id', 'classification'])
            ->pluck('classification', 'worksite_id');

        $today = today();

        return $worksites->map(function (Worksite $worksite) use ($contracts, $latestEvidence, $today) {
            $overdue = $worksite->contracts->contains(
                fn (WorksiteContract $link) => $contracts->get($link->secop_contract_id)?->isOverdueInExecution($today) ?? false,
            );
            $color = PinColor::ofEvidence($latestEvidence->get($worksite->id))->worst($overdue ? PinColor::Red : PinColor::Green);
            $place = $worksite->location()->approximate(); // R-PRIV-02: la ancló el primer veedor, donde estaba

            return ['id' => $worksite->id, 'lat' => $place->latitude, 'lng' => $place->longitude, 'color_pin' => $color->value];
        })->all();
    }
}
