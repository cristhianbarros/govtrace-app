<?php

namespace App\Application\Contracts;

use App\Application\Reports\NearbyWorksites;
use App\Domain\Configuration\Parameters;
use App\Domain\Contracts\Contract;
use App\Domain\Contracts\SearchText;
use App\Domain\Contracts\SecopContractStatus;
use App\Domain\Contracts\WorkType;
use App\Domain\Geography\Department;
use App\Domain\Geography\GeoPoint;
use App\Domain\Geography\Municipality;
use App\Domain\Geography\PlaceName;
use App\Domain\Organization\WatchedTerritories;
use App\Domain\Worksites\Worksite;
use App\Domain\Worksites\WorksiteContract;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * It. 47a — "Encontrar la obra en campo" (US-016, US-019; V18, V19): what the
 * veedor sees on opening "Nuevo reporte". The reportable works of his
 * municipality — decided by his GPS, or the first of the territory if it
 * can't be — with filters (kind of work, situation, entity), overdue first,
 * 20 at a time, and the search without accents. With a reading of 50 m or
 * less, the works near him on top. His location is used, never kept.
 */
final class BrowseReportableContracts
{
    public const PAGE = 20;

    /** Enough to know the municipality, not to find a work 500 m away. */
    public const MUNICIPALITY_ACCURACY_METERS = 5_000;

    /** US-008: the precision a report needs, and the nearby works too. */
    public const NEARBY_ACCURACY_METERS = 50;

    public const SITUATIONS = ['all', 'overdue', 'in_progress', 'finished'];

    private const MIN_KEYWORD = 3;

    /**
     * @param  array{latitude?: float|null, longitude?: float|null, accuracy?: float|null, municipality?: string|null, work_type?: string|null, situation?: string|null, entity?: string|null, q?: string|null, scope?: string|null, page?: int|null}  $request
     * @return array<string, mixed>
     */
    public function handle(Tenant $tenant, array $request): array
    {
        $today = now()->startOfDay();
        $watched = WatchedTerritories::ofActiveOrganizations($tenant->getTenantKey());
        $reportable = fn (): Builder => Contract::query()->inTerritory($watched)->reportableAt(now());

        $here = isset($request['latitude'], $request['longitude']) ? new GeoPoint((float) $request['latitude'], (float) $request['longitude']) : null;
        $accuracy = isset($request['accuracy']) ? (float) $request['accuracy'] : null;

        $choices = $this->choices($reportable());
        [$municipality, $notice] = $this->municipality($request['municipality'] ?? null, $here, $accuracy, $watched, $choices);

        $inScope = $reportable();
        if (($request['scope'] ?? 'municipality') !== 'territory' && $municipality !== null) {
            $this->inPlace($inScope, $municipality['code']);
        }

        $entities = (clone $inScope)->select('entity_name', DB::raw('count(*) as total'))
            ->groupBy('entity_name')->orderByDesc('total')->orderBy('entity_name')->get()
            ->map(fn ($row) => ['name' => $row->entity_name, 'count' => (int) $row->total])->all();

        $list = (clone $inScope);
        $this->filtered($list, $request, $today);
        $page = max(1, (int) ($request['page'] ?? 1));
        $contracts = $this->ordered($list, $today)->with('municipality')
            ->offset(($page - 1) * self::PAGE)->limit(self::PAGE + 1)->get();

        return [
            'municipality' => $municipality,
            'notice' => $notice,
            'municipalities' => array_values($choices->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->all()),
            'entities' => $entities,
            'work_types' => array_map(fn (WorkType $type) => ['key' => $type->value, 'label' => $type->label()], WorkType::cases()),
            'data' => $this->presented($contracts->take(self::PAGE), $today),
            'has_more' => $contracts->count() > self::PAGE,
            'nearby' => $here !== null && $accuracy !== null && $accuracy <= self::NEARBY_ACCURACY_METERS
                ? $this->nearby($here)
                : null,
        ];
    }

    /**
     * The places of the territory that have reportable works, with how many:
     * each municipality, and a department's own contracts (its Gobernación,
     * no municipality) as one more choice.
     *
     * @return Collection<string, array{code: string, name: string, count: int}>
     */
    private function choices(Builder $reportable): Collection
    {
        $counts = $reportable->select('department_code', 'municipality_code', DB::raw('count(*) as total'))
            ->groupBy('department_code', 'municipality_code')->get();

        $municipalities = Municipality::query()->whereIn('code', $counts->pluck('municipality_code')->filter())->pluck('name', 'code');
        $departments = Department::query()->whereIn('code', $counts->whereNull('municipality_code')->pluck('department_code'))->pluck('name', 'code');

        return $counts->mapWithKeys(fn ($row) => $row->municipality_code !== null
            ? [$row->municipality_code => ['code' => $row->municipality_code, 'name' => PlaceName::forDisplay($municipalities[$row->municipality_code] ?? $row->municipality_code), 'count' => (int) $row->total]]
            : ["dep:{$row->department_code}" => ['code' => "dep:{$row->department_code}", 'name' => PlaceName::forDisplay($departments[$row->department_code] ?? $row->department_code).': contratos de la gobernación', 'count' => (int) $row->total]]);
    }

    /**
     * The one chosen, if it is in the territory; else the one whose seat is
     * nearest to the veedor (30 km at most, as for anchoring a work, it. 46f);
     * else the first of the territory by its DIVIPOLA code, with a notice.
     *
     * @param  Collection<string, array{code: string, name: string, count: int}>  $choices
     * @return array{0: array{code: string, name: string}|null, 1: string|null}
     */
    private function municipality(?string $asked, ?GeoPoint $here, ?float $accuracy, WatchedTerritories $watched, Collection $choices): array
    {
        if ($asked !== null && $choices->has($asked)) {
            return [['code' => $asked, 'name' => $choices[$asked]['name']], null];
        }

        if ($asked === null && $here !== null && $accuracy !== null && $accuracy <= self::MUNICIPALITY_ACCURACY_METERS) {
            $radius = (int) Parameters::current('anchor_municipality_radius_km') * 1000;
            $nearest = Municipality::query()
                ->where(fn (Builder $query) => $query->whereIn('department_code', $watched->departmentCodes())->orWhereIn('code', $watched->municipalityCodes()))
                ->whereNotNull('latitude')->whereNotNull('longitude')
                ->get()
                ->map(fn (Municipality $municipality) => [$municipality, $municipality->seat()->distanceInMetersTo($here)])
                ->sortBy(1)
                ->first();

            if ($nearest !== null && $nearest[1] <= $radius) {
                return [['code' => $nearest[0]->code, 'name' => PlaceName::forDisplay($nearest[0]->name)], null];
            }
        }

        $first = $choices->sortKeys()->reject(fn (array $choice) => str_starts_with($choice['code'], 'dep:'))->first() ?? $choices->sortKeys()->first();
        if ($first === null) {
            return [null, null];
        }

        return [
            ['code' => $first['code'], 'name' => $first['name']],
            "No pudimos saber en qué municipio está. Le mostramos las obras de {$first['name']}: elija el suyo.",
        ];
    }

    private function inPlace(Builder $query, string $code): void
    {
        if (str_starts_with($code, 'dep:')) {
            $query->whereNull('municipality_code')->where('department_code', substr($code, 4));
        } else {
            $query->where('municipality_code', $code);
        }
    }

    /** @param  array<string, mixed>  $request */
    private function filtered(Builder $query, array $request, \DateTimeInterface $today): void
    {
        $active = SecopContractStatus::ACTIVE;

        match ($request['situation'] ?? 'all') {
            'overdue' => $query->statusIn($active)->whereDate('end_date', '<', $today),
            'in_progress' => $query->statusIn($active)->where(fn (Builder $query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today)),
            'finished' => $query->statusIn(SecopContractStatus::CLOSED),
            default => null,
        };

        if (! empty($request['work_type'])) {
            $query->where('work_type', $request['work_type']);
        }

        if (! empty($request['entity'])) {
            $query->where('entity_name', $request['entity']);
        }

        $keyword = SearchText::of($request['q'] ?? null);
        if (mb_strlen($keyword) >= self::MIN_KEYWORD) {
            $query->where('search_text', 'like', '%'.addcslashes($keyword, '%_\\').'%');
        }
    }

    /**
     * Overdue first, the longest overdue on top; then the ones running, the
     * one ending soonest on top; then those with no end date; last, the ones
     * finished lately, the most recent on top (decided in the discovery).
     */
    private function ordered(Builder $query, \DateTimeInterface $today): Builder
    {
        $active = implode(', ', array_fill(0, count(SecopContractStatus::ACTIVE), '?'));
        $day = $today->format('Y-m-d');
        $rank = "case when lower(status) in ({$active}) and end_date < ? then 0 when lower(status) in ({$active}) and end_date >= ? then 1 when lower(status) in ({$active}) then 2 else 3 end";
        $bindings = [...SecopContractStatus::ACTIVE, $day, ...SecopContractStatus::ACTIVE, $day, ...SecopContractStatus::ACTIVE];

        return $query
            ->orderByRaw("{$rank} asc", $bindings)
            ->orderByRaw("case when ({$rank}) in (0, 1) then end_date end asc nulls last", $bindings)
            ->orderByRaw("case when ({$rank}) = 3 then end_date end desc nulls last", $bindings)
            ->orderByDesc('signed_at')
            ->orderBy('secop_contract_id');
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     * @return list<array<string, mixed>>
     */
    private function presented(Collection $contracts, \DateTimeInterface $today): array
    {
        // Each organization has its own worksite sheet (R-INT-05): whether this one located it, and its name.
        $sheets = WorksiteContract::query()->whereIn('secop_contract_id', $contracts->pluck('secop_contract_id'))
            ->with('worksite')->get()->keyBy('secop_contract_id');

        return $contracts->map(function (Contract $contract) use ($sheets, $today) {
            $worksite = $sheets->get($contract->secop_contract_id)?->worksite;

            return [
                ...$contract->only(['secop_contract_id', 'object', 'entity_name', 'contractor_name', 'process_number', 'status']),
                'name' => $worksite?->name,
                'municipality' => $contract->municipality ? PlaceName::forDisplay($contract->municipality->name) : null,
                'work_type' => $contract->work_type,
                'work_type_label' => WorkType::tryFrom((string) $contract->work_type)?->label() ?? WorkType::Other->label(),
                'situation' => $this->situation($contract, $today),
                'end_date' => $contract->end_date?->toDateString(),
                'located' => $worksite instanceof Worksite && $worksite->latitude !== null,
            ];
        })->values()->all();
    }

    private function situation(Contract $contract, \DateTimeInterface $today): string
    {
        if (in_array(mb_strtolower((string) $contract->status), SecopContractStatus::CLOSED, true)) {
            return 'finished';
        }

        return match (true) {
            $contract->end_date === null => 'no_end_date',
            $contract->end_date->lt($today) => 'overdue',
            default => 'in_progress',
        };
    }

    /** @return list<array<string, mixed>> */
    private function nearby(GeoPoint $here): array
    {
        return array_map(fn (array $suggestion) => [
            'worksite_id' => $suggestion['worksite']->public_id,
            'name' => $suggestion['worksite']->name ?? $suggestion['contract']->object,
            'distance_meters' => $suggestion['distance_meters'],
            'contract' => $suggestion['contract']->only(['secop_contract_id', 'object', 'entity_name', 'contractor_name', 'process_number', 'status']),
        ], (new NearbyWorksites)->handle($here));
    }
}
