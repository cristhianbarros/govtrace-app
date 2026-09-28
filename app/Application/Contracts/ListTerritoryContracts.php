<?php

namespace App\Application\Contracts;

use App\Domain\Contracts\Contract;
use App\Domain\Organization\WatchedTerritories;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * US-015: el listado paginado de contratos del territorio de una
 * organización, para el Administrador. El truncado del objeto a 50
 * caracteres y el tooltip son de la pantalla (it. 18) — aquí se entrega
 * el texto completo.
 */
class ListTerritoryContracts
{
    private const PER_PAGE = 20;

    /**
     * @param  string  $sortBy  'signed_at' (por defecto) o 'value'
     * @param  string  $direction  'asc' o 'desc'
     */
    public function handle(Tenant $tenant, string $sortBy = 'signed_at', string $direction = 'desc'): LengthAwarePaginator
    {
        $watched = WatchedTerritories::ofActiveOrganizations($tenant->id);

        return Contract::query()
            ->where(function ($query) use ($watched) {
                // Un departamento vigilado entero trae su Gobernación
                // (municipality_code nulo) y todos sus municipios; un
                // municipio vigilado por su cuenta trae solo el suyo.
                $query->whereIn('department_code', $watched->departmentCodes())
                    ->orWhereIn('municipality_code', $watched->municipalityCodes());
            })
            ->orderBy($sortBy, $direction)
            ->paginate(self::PER_PAGE);
    }
}
