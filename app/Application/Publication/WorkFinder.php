<?php

namespace App\Application\Publication;

use App\Domain\Contracts\Contract;
use App\Domain\Contracts\ContractFollower;
use App\Domain\Contracts\SearchText;
use App\Domain\Geography\Department;
use App\Domain\Geography\Municipality;
use App\Domain\Geography\PlaceName;
use App\Domain\Organization\OrganizationStatus;
use App\Domain\Organization\OrganizationTerritory;
use App\Domain\Organization\WatchedTerritories;
use App\Infrastructure\Tenancy\Tenant;
use App\Infrastructure\Tenancy\TenantUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * It. 59 (US-066): on GovTrace's Home page, the citizen looks for the work
 * among the SECOP contracts and GovTrace says which veedurías can take their
 * report, by public rules, never a hidden score:
 * - required: its territory covers the work, and it is active (a suspended
 *   one doesn't receive reports);
 * - first, the one that already follows the work (has its sheet);
 * - then, the one with the more specific territory (the municipality before
 *   the whole department);
 * - then, the one that published evidence in the last 90 days;
 * - in a tie, alphabetical order, shown as equals.
 * It suggests, it doesn't choose: choosing the veeduría is choosing who gets
 * the report and the email (Ley 1581). R-MAP-01 holds: contracts, who covers
 * them and a count, never the evidence of several veedurías together.
 *
 * In the central domain.
 */
final class WorkFinder
{
    public const PAGE = 20;

    public const ACTIVE_DAYS = 90;

    private const MIN_KEYWORD = 3;

    /**
     * The works someone can report today, in the territory of an active veeduría: by name without accents and by municipality, 20 at a time.
     *
     * @param  array{q?: string|null, municipality?: string|null, page?: int|string|null}  $filters
     * @return array{data: list<array{contract_id: string, name: string, municipality: ?string, entity_name: ?string}>, has_more: bool}
     */
    public function search(array $filters): array
    {
        $query = $this->reportable();

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
        $municipalities = Municipality::query()->whereIn('code', $shown->pluck('municipality_code')->filter())->pluck('name', 'code');

        return [
            'data' => $shown->map(fn (Contract $contract) => [
                'contract_id' => $contract->secop_contract_id,
                'name' => $contract->object,
                'municipality' => isset($municipalities[$contract->municipality_code]) ? PlaceName::forDisplay($municipalities[$contract->municipality_code]) : null,
                'entity_name' => $contract->entity_name,
            ])->values()->all(),
            'has_more' => $contracts->count() > self::PAGE,
        ];
    }

    /**
     * The municipalities some active veeduría covers, with their seat: the browser compares the citizen's location with them, so it never reaches the server.
     *
     * @return list<array{code: string, name: string, department: string, latitude: float, longitude: float}>
     */
    public function municipalities(): array
    {
        $watched = WatchedTerritories::ofActiveOrganizations();
        $municipalities = Municipality::query()
            ->where(fn (Builder $query) => $query->whereIn('department_code', $watched->departmentCodes())->orWhereIn('code', $watched->municipalityCodes()))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();
        $departments = Department::query()->whereIn('code', $municipalities->pluck('department_code')->unique())->pluck('name', 'code');

        return $municipalities
            ->map(fn (Municipality $municipality) => [
                'code' => $municipality->code,
                'name' => PlaceName::forDisplay($municipality->name),
                'department' => PlaceName::forDisplay($departments[$municipality->department_code] ?? ''),
                'latitude' => (float) $municipality->latitude,
                'longitude' => (float) $municipality->longitude,
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Who can take a report about the work, ordered by the rules; null if no one can report it today.
     *
     * @return array{contract: array<string, ?string>, organizations: list<array{name: string, url: string, reasons: list<string>, suggested: bool}>}|null
     */
    public function whoCanHelp(string $secopContractId): ?array
    {
        $contract = Contract::query()->reportableAt(now())->where('secop_contract_id', $secopContractId)->first();
        if ($contract === null) {
            return null;
        }

        $municipality = $contract->municipality_code !== null ? Municipality::query()->find($contract->municipality_code) : null;
        $department = Department::query()->find($contract->department_code);
        $municipalityName = $municipality !== null ? PlaceName::forDisplay($municipality->name) : null;
        $departmentName = $department !== null ? PlaceName::forDisplay($department->name) : null;

        return [
            'contract' => [
                'contract_id' => $contract->secop_contract_id,
                'name' => $contract->object,
                'municipality' => $municipalityName,
                'department' => $departmentName,
                'entity_name' => $contract->entity_name,
            ],
            'organizations' => $this->ranked($contract, $municipalityName, $departmentName),
        ];
    }

    /** @return list<array{name: string, url: string, reasons: list<string>, suggested: bool}> */
    private function ranked(Contract $contract, ?string $municipalityName, ?string $departmentName): array
    {
        // Un contrato de la gobernación (sin municipio) lo cubre solo quien vigila el departamento entero.
        $coverage = OrganizationTerritory::query()
            ->join('tenants', 'tenants.id', '=', 'organization_territories.tenant_id')
            ->where('tenants.status', OrganizationStatus::Active->value)
            ->where(fn (Builder $query) => $query
                ->where('organization_territories.department_code', $contract->department_code)
                ->when($contract->municipality_code !== null, fn (Builder $query) => $query->orWhere('organization_territories.municipality_code', $contract->municipality_code)))
            ->get(['organization_territories.tenant_id', 'organization_territories.municipality_code'])
            ->groupBy('tenant_id');
        if ($coverage->isEmpty()) {
            return [];
        }

        $organizations = Tenant::query()->with('domains')->whereIn('id', $coverage->keys())->get()->keyBy('id');
        $followers = ContractFollower::query()->where('secop_contract_id', $contract->secop_contract_id)->whereIn('tenant_id', $coverage->keys())->get()->keyBy('tenant_id');
        $active = ContractFollower::query()
            ->whereIn('tenant_id', $coverage->keys())
            ->where('last_published_at', '>=', now()->subDays(self::ACTIVE_DAYS))
            ->distinct()
            ->pluck('tenant_id')
            ->flip();

        $entries = $coverage->map(function ($rows, string $tenantId) use ($organizations, $followers, $active, $contract, $municipalityName, $departmentName) {
            $follower = $followers->get($tenantId);
            $specific = $contract->municipality_code !== null && $rows->contains('municipality_code', $contract->municipality_code);
            $reasons = array_values(array_filter([
                $follower === null ? null : ($follower->published_evidences === 0
                    ? 'Ya sigue esta obra'
                    : 'Ya sigue esta obra: '.$follower->published_evidences.' '.($follower->published_evidences === 1 ? 'evidencia publicada' : 'evidencias publicadas')),
                $specific ? "Vigila {$municipalityName}" : "Vigila todo {$departmentName}",
                $active->has($tenantId) ? 'Publicó evidencias en los últimos '.self::ACTIVE_DAYS.' días' : null,
            ]));

            return [
                'rank' => [$follower !== null, $specific, $active->has($tenantId)],
                'name' => $organizations[$tenantId]->displayName(),
                'url' => TenantUrl::to($organizations[$tenantId]->domains->first()->domain, '/contract/'.$contract->secop_contract_id.'#informar'),
                'reasons' => $reasons,
            ];
        })->values()->all();

        usort($entries, fn (array $a, array $b) => $b['rank'] <=> $a['rank'] ?: strnatcasecmp(Str::ascii($a['name']), Str::ascii($b['name'])));

        // Se sugiere la primera solo si gana por una regla; si empata con la segunda, van como iguales.
        $winner = count($entries) === 1 || $entries[0]['rank'] !== $entries[1]['rank'];

        return array_map(fn (array $entry, int $index) => [
            'name' => $entry['name'],
            'url' => $entry['url'],
            'reasons' => $entry['reasons'],
            'suggested' => $index === 0 && $winner,
        ], $entries, array_keys($entries));
    }

    private function reportable(): Builder
    {
        return Contract::query()->inTerritory(WatchedTerritories::ofActiveOrganizations())->reportableAt(now());
    }
}
