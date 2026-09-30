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
 *
 * It. 40c: con $words, solo los que las contienen en su objeto, su
 * contratista, su número de proceso o su id de SECOP (sin distinguir
 * mayúsculas), como la búsqueda del veedor (US-016).
 */
class ListTerritoryContracts
{
    private const PER_PAGE = 20;

    /**
     * @param  string  $sortBy  'signed_at' (por defecto) o 'value'
     * @param  string  $direction  'asc' o 'desc'
     */
    public function handle(Tenant $tenant, string $sortBy = 'signed_at', string $direction = 'desc', ?string $words = null): LengthAwarePaginator
    {
        $words = trim((string) $words);

        return Contract::query()
            ->inTerritory(WatchedTerritories::ofActiveOrganizations($tenant->id))
            ->when($words !== '', fn ($query) => $query->where(function ($query) use ($words) {
                $like = '%'.addcslashes($words, '%_\\').'%';
                $query->where('object', 'ilike', $like)
                    ->orWhere('contractor_name', 'ilike', $like)
                    ->orWhere('process_number', 'ilike', $like)
                    ->orWhere('secop_contract_id', 'ilike', $like);
            }))
            ->orderBy($sortBy, $direction)
            ->paginate(self::PER_PAGE);
    }
}
