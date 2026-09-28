<?php

namespace App\Application\Contracts;

use App\Domain\Contracts\Contract;
use App\Domain\Organization\WatchedTerritories;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Collection;

/**
 * US-016 (R-VC-04): busca, dentro del territorio de la organización, los
 * contratos que un Veedor puede elegir para reportar. El debounce de
 * 300 ms es de la pantalla de la PWA (it. 16); aquí se exige el mínimo
 * de 3 caracteres, y la regla de qué contratos son seleccionables vive
 * en Contract::scopeReportableAt() — la misma que aplica CreateReport.
 */
class SearchSelectableContracts
{
    private const MIN_CHARACTERS = 3;

    /**
     * @return Collection<int, Contract>
     */
    public function handle(Tenant $tenant, string $keyword): Collection
    {
        if (mb_strlen(trim($keyword)) < self::MIN_CHARACTERS) {
            return new Collection;
        }

        return Contract::query()
            ->inTerritory(WatchedTerritories::ofActiveOrganizations($tenant->id))
            ->reportableAt(now())
            ->where(function ($query) use ($keyword) {
                $query->where('object', 'ilike', "%{$keyword}%")
                    ->orWhere('contractor_name', 'ilike', "%{$keyword}%")
                    ->orWhere('process_number', 'ilike', "%{$keyword}%");
            })
            ->get();
    }
}
