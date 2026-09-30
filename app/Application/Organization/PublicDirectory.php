<?php

namespace App\Application\Organization;

use App\Domain\Geography\Department;
use App\Domain\Geography\Municipality;
use App\Domain\Geography\PlaceName;
use App\Domain\Organization\OrganizationStatus;
use App\Domain\Organization\OrganizationTerritory;
use App\Infrastructure\Tenancy\Tenant;
use App\Infrastructure\Tenancy\TenantUrl;

/**
 * It. 40d (V5 de docs/mapa-funcional.md): el directorio del Inicio de GovTrace
 * — cada veeduría abierta, con el nombre que eligió, su territorio y el
 * enlace a su mapa. No mezcla mapas (R-MAP-01): solo lleva a cada uno. Una
 * suspendida sigue, con su aviso, porque su mapa sigue abierto (R-AUD-01); una
 * dada de baja, no. Cuatro consultas, sea cual sea el número de veedurías.
 */
class PublicDirectory
{
    /** @return list<array{name: string, territory: ?string, url: string, suspended: bool}> */
    public function handle(): array
    {
        $organizations = Tenant::query()
            ->whereIn('status', [OrganizationStatus::Active->value, OrganizationStatus::Suspended->value])
            ->with('domains')
            ->get();
        $territories = OrganizationTerritory::query()->whereIn('tenant_id', $organizations->modelKeys())->get()->groupBy('tenant_id');
        $departments = Department::query()->whereIn('code', $territories->flatten()->pluck('department_code')->filter())->pluck('name', 'code');
        $municipalities = Municipality::query()->whereIn('code', $territories->flatten()->pluck('municipality_code')->filter())->pluck('name', 'code');

        return $organizations
            ->map(fn (Tenant $organization) => [
                'name' => $organization->displayName(),
                'territory' => $this->territoryOf($territories->get($organization->id), $departments, $municipalities),
                'url' => TenantUrl::to($organization->domains->first()->domain, '/'),
                'suspended' => $organization->status === OrganizationStatus::Suspended->value,
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /** "Magdalena", or "Santa Marta, Ciénaga": each municipality by its name, each whole department by its own. */
    private function territoryOf($places, $departments, $municipalities): ?string
    {
        if ($places === null || $places->isEmpty()) {
            return null;
        }

        return $places
            ->map(fn (OrganizationTerritory $place) => $place->municipality_code !== null
                ? $municipalities->get($place->municipality_code)
                : $departments->get($place->department_code))
            ->filter()
            ->map(fn (string $name) => PlaceName::forDisplay($name))
            ->implode(', ');
    }
}
