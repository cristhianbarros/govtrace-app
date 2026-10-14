<?php

namespace App\Application\Publication;

use App\Application\CitizenReports\ReportedWork;
use App\Domain\Contracts\Contract;
use App\Domain\Contracts\SearchText;
use App\Domain\Geography\Municipality;
use App\Domain\Geography\PlaceName;
use App\Domain\Worksites\Worksite;
use App\Domain\Worksites\WorksiteContract;

/**
 * It. 58 (US-059-LEG): the works of the territory that are not on the public
 * map yet — no veedor has reported them from the place, or their location is
 * still to be confirmed —, so the citizen can inform the veeduría about any
 * of them. The same contracts a veedor may report (R-SEC-07), the most
 * recently signed first, 20 at a time, by name without accents and by
 * municipality.
 */
final class UnlocatedWorksites
{
    public const PAGE = 20;

    private const MIN_KEYWORD = 3;

    /**
     * @param  array{q?: string|null, municipality?: string|null, page?: int|string|null}  $filters
     * @return array{data: list<array{contract_id: string, worksite_id: ?string, name: string, municipality: ?string, entity_name: ?string}>, has_more: bool}
     */
    public function handle(array $filters): array
    {
        $onTheMap = WorksiteContract::query()
            ->whereIn('worksite_id', Worksite::query()->whereNotNull('latitude')->select('id'))
            ->pluck('secop_contract_id')
            ->all();

        $query = ReportedWork::reportableContracts()->whereNotIn('secop_contract_id', $onTheMap);

        $keyword = SearchText::of($filters['q'] ?? null);
        if (mb_strlen($keyword) >= self::MIN_KEYWORD) {
            $query->where('search_text', 'like', '%'.addcslashes($keyword, '%_\\').'%');
        }
        if (! empty($filters['municipality'])) {
            $query->where('municipality_code', $filters['municipality']);
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $contracts = $query->orderByDesc('signed_at')->orderBy('secop_contract_id')
            ->offset(($page - 1) * self::PAGE)->limit(self::PAGE + 1)
            ->get(['secop_contract_id', 'object', 'entity_name', 'municipality_code']);
        $shown = $contracts->take(self::PAGE);

        $sheets = WorksiteContract::query()->whereIn('secop_contract_id', $shown->pluck('secop_contract_id'))->with('worksite')->get()->keyBy('secop_contract_id');
        $municipalities = Municipality::query()->whereIn('code', $shown->pluck('municipality_code')->filter())->pluck('name', 'code');

        return [
            'data' => $shown->map(function (Contract $contract) use ($sheets, $municipalities) {
                $worksite = $sheets->get($contract->secop_contract_id)?->worksite;

                return [
                    'contract_id' => $contract->secop_contract_id,
                    'worksite_id' => $worksite?->public_id,
                    'name' => $worksite?->name ?? $contract->object,
                    'municipality' => isset($municipalities[$contract->municipality_code]) ? PlaceName::forDisplay($municipalities[$contract->municipality_code]) : null,
                    'entity_name' => $contract->entity_name,
                ];
            })->values()->all(),
            'has_more' => $contracts->count() > self::PAGE,
        ];
    }
}
