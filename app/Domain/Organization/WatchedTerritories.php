<?php

namespace App\Domain\Organization;

use App\Domain\Geography\Department;
use App\Domain\Geography\Municipality;
use Illuminate\Support\Collection;

/**
 * The territory that at least one ACTIVE organization watches (US-012):
 * whole departments plus single municipalities. R-SEC-02 — SECOP is only
 * asked about, and contracts are only stored for, what is in here;
 * R-AUD-06 — a place that falls out of it keeps its contracts, frozen.
 */
final class WatchedTerritories
{
    /**
     * @param  list<string>  $departmentCodes  departments watched as a whole
     * @param  list<string>  $municipalityCodes  municipalities watched on their own
     */
    public function __construct(
        private readonly array $departmentCodes,
        private readonly array $municipalityCodes,
    ) {}

    /**
     * @param  string|null  $organizationId  only that organization's territory (immediate sync); null = every active one (nightly run).
     */
    public static function ofActiveOrganizations(?string $organizationId = null): self
    {
        $rows = OrganizationTerritory::query()
            ->join('tenants', 'tenants.id', '=', 'organization_territories.tenant_id')
            ->where('tenants.status', 'active')
            ->when($organizationId, fn ($query) => $query->where('organization_territories.tenant_id', $organizationId))
            ->get(['organization_territories.department_code', 'organization_territories.municipality_code']);

        return new self(
            $rows->pluck('department_code')->filter()->unique()->values()->all(),
            $rows->pluck('municipality_code')->filter()->unique()->values()->all(),
        );
    }

    public function covers(Municipality $municipality): bool
    {
        return in_array($municipality->department_code, $this->departmentCodes, true)
            || in_array($municipality->code, $this->municipalityCodes, true);
    }

    /**
     * Departments to ask SECOP about: the ones watched whole, plus the
     * ones holding a watched municipality.
     *
     * @return Collection<int, Department>
     */
    public function departmentsToQuery(): Collection
    {
        $codes = Municipality::query()
            ->whereIn('code', $this->municipalityCodes)
            ->pluck('department_code')
            ->merge($this->departmentCodes)
            ->unique();

        return Department::query()->whereIn('code', $codes)->orderBy('code')->get();
    }
}
