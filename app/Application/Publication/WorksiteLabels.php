<?php

namespace App\Application\Publication;

use App\Domain\Contracts\Contract;
use App\Domain\Geography\Municipality;
use App\Domain\Geography\PlaceName;
use App\Domain\Worksites\Worksite;
use App\Domain\Worksites\WorksiteContract;

/**
 * How the exports name a worksite (US-050-RPT, US-052-RPT): its name — or
 * the object of its first contract, as the public view does —, its SECOP
 * contracts and their municipalities. In bulk: four queries for any number
 * of worksites.
 */
final class WorksiteLabels
{
    /**
     * @param  list<int>  $worksiteIds
     * @return array<int, array{name: string, contracts: string, municipalities: string}>
     */
    public static function of(array $worksiteIds): array
    {
        $links = WorksiteContract::query()->whereIn('worksite_id', $worksiteIds)->orderBy('id')->get(['worksite_id', 'secop_contract_id'])->groupBy('worksite_id');
        $names = Worksite::query()->whereIn('id', $worksiteIds)->pluck('name', 'id');
        $contracts = Contract::query()
            ->whereIn('secop_contract_id', $links->flatten(1)->pluck('secop_contract_id'))
            ->get(['secop_contract_id', 'object', 'municipality_code'])
            ->keyBy('secop_contract_id');
        $municipalities = Municipality::query()->whereIn('code', $contracts->pluck('municipality_code')->filter()->unique())->pluck('name', 'code');

        $labels = [];
        foreach ($worksiteIds as $id) {
            $ids = ($links[$id] ?? collect())->pluck('secop_contract_id');
            $own = $ids->map(fn (string $secopId) => $contracts->get($secopId))->filter();

            $labels[$id] = [
                'name' => $names[$id] ?? $own->first()?->object ?? '',
                'contracts' => $ids->implode('; '),
                'municipalities' => $own->pluck('municipality_code')->filter()->unique()
                    ->map(fn (string $code) => PlaceName::forDisplay($municipalities[$code] ?? $code))
                    ->implode('; '),
            ];
        }

        return $labels;
    }
}
