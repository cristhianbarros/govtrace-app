<?php

namespace App\Application\Publication;

use App\Domain\Contracts\Contract;
use App\Domain\Geography\Municipality;
use App\Domain\Geography\PlaceName;
use App\Domain\Reports\Report;
use App\Domain\Worksites\PinColor;
use App\Domain\Worksites\Worksite;
use App\Domain\Worksites\WorksiteContract;
use Carbon\CarbonImmutable;

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
    private const TIMEZONE = 'America/Bogota';

    /**
     * US-028: the filters, all optional — status (green, yellow, red), the
     * dates of their published evidences (from, to, in Colombia), budget
     * (min_value: the sum of the contracts of the worksite, more than it)
     * and municipality (one of its contracts there).
     *
     * @param  array{status?: string, from?: string, to?: string, min_value?: int|float|string, municipality?: string}  $filters
     * @return list<array{id: int, lat: float, lng: float, color_pin: string}>
     */
    public function pins(array $filters = []): array
    {
        return array_map(fn (array $candidate) => $candidate['pin'], $this->candidates($filters));
    }

    /**
     * It. 40c: the map as a list — the same worksites and filters as pins(),
     * each with its name (its own, or its first contract's object) and the
     * municipality of that contract. Asked for when the list is opened, not
     * with the map (R-MAP-02).
     *
     * @param  array{status?: string, from?: string, to?: string, min_value?: int|float|string, municipality?: string}  $filters
     * @return list<array{id: int, name: ?string, municipality: ?string, color_pin: string}>
     */
    public function listing(array $filters = []): array
    {
        $candidates = $this->candidates($filters);
        $names = Municipality::query()->whereIn('code', array_filter(array_column($candidates, 'municipality')))->pluck('name', 'code');

        return array_map(fn (array $candidate) => [
            'id' => $candidate['pin']['id'],
            'name' => $candidate['name'],
            'municipality' => isset($names[$candidate['municipality']]) ? PlaceName::forDisplay($names[$candidate['municipality']]) : null,
            'color_pin' => $candidate['pin']['color_pin'],
        ], $candidates);
    }

    /** @return list<array{pin: array{id: int, lat: float, lng: float, color_pin: string}, name: ?string, municipality: ?string}> */
    private function candidates(array $filters): array
    {
        $worksites = Worksite::query()->whereNotNull('latitude')->with('contracts')->orderBy('id')->get();

        $contracts = Contract::query()
            ->whereIn('secop_contract_id', $worksites->flatMap->contracts->pluck('secop_contract_id'))
            ->get(['secop_contract_id', 'status', 'end_date', 'value', 'municipality_code', 'object'])
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
        $withEvidenceInRange = $this->withEvidenceBetween($filters['from'] ?? null, $filters['to'] ?? null);

        return $worksites
            ->map(function (Worksite $worksite) use ($contracts, $latestEvidence, $today) {
                $own = $worksite->contracts->map(fn (WorksiteContract $link) => $contracts->get($link->secop_contract_id))->filter();
                $overdue = $own->contains(fn (Contract $contract) => $contract->isOverdueInExecution($today));
                $color = PinColor::ofEvidence($latestEvidence->get($worksite->id))->worst($overdue ? PinColor::Red : PinColor::Green);
                $place = $worksite->location()->approximate(); // R-PRIV-02: la ancló el primer veedor, donde estaba

                $first = $contracts->get($worksite->contracts->sortBy('id')->first()?->secop_contract_id);

                return [
                    'pin' => ['id' => $worksite->id, 'lat' => $place->latitude, 'lng' => $place->longitude, 'color_pin' => $color->value],
                    'budget' => $own->sum(fn (Contract $contract) => (float) $contract->value),
                    'municipalities' => $own->pluck('municipality_code')->filter()->all(),
                    'name' => $worksite->name ?? $first?->object,
                    'municipality' => $first?->municipality_code,
                ];
            })
            ->filter(fn (array $candidate) => ! isset($filters['status']) || $candidate['pin']['color_pin'] === $filters['status'])
            ->filter(fn (array $candidate) => $withEvidenceInRange === null || isset($withEvidenceInRange[$candidate['pin']['id']]))
            ->filter(fn (array $candidate) => ! isset($filters['min_value']) || $candidate['budget'] > (float) $filters['min_value'])
            ->filter(fn (array $candidate) => ! isset($filters['municipality']) || in_array($filters['municipality'], $candidate['municipalities'], true))
            ->map(fn (array $candidate) => ['pin' => $candidate['pin'], 'name' => $candidate['name'], 'municipality' => $candidate['municipality']])
            ->values()
            ->all();
    }

    /** US-028: the worksites with a published evidence captured between those dates of Colombia; null with no dates. */
    private function withEvidenceBetween(?string $from, ?string $to): ?array
    {
        if ($from === null && $to === null) {
            return null;
        }

        return Report::query()
            ->onPublicMap()
            ->when($from, fn ($query) => $query->where('captured_at', '>=', CarbonImmutable::parse($from, self::TIMEZONE)->startOfDay()->utc()))
            ->when($to, fn ($query) => $query->where('captured_at', '<=', CarbonImmutable::parse($to, self::TIMEZONE)->endOfDay()->utc()))
            ->distinct()
            ->pluck('worksite_id')
            ->flip()
            ->all();
    }

    /** US-028: the municipalities of the worksites on the map, for the filter. @return list<array{code: string, name: string}> */
    public function municipalities(): array
    {
        $codes = Contract::query()
            ->whereIn('secop_contract_id', WorksiteContract::query()
                ->whereIn('worksite_id', Worksite::query()->whereNotNull('latitude')->select('id'))
                ->pluck('secop_contract_id'))
            ->whereNotNull('municipality_code')
            ->distinct()
            ->pluck('municipality_code');

        return Municipality::query()->whereIn('code', $codes)->orderBy('name')->get(['code', 'name'])
            ->map(fn (Municipality $municipality) => ['code' => $municipality->code, 'name' => PlaceName::forDisplay($municipality->name)])
            ->all();
    }
}
