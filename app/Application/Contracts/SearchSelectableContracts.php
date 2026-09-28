<?php

namespace App\Application\Contracts;

use App\Domain\Configuration\Parameters;
use App\Domain\Contracts\Contract;
use App\Domain\Organization\WatchedTerritories;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Collection;

/**
 * US-016 (R-VC-04): busca, dentro del territorio de la organización, los
 * contratos que un Veedor puede elegir para reportar. El debounce de
 * 300 ms es de la pantalla de la PWA (it. 16); aquí se exige el mínimo
 * de 3 caracteres y se aplica la regla de qué estados son seleccionables.
 */
class SearchSelectableContracts
{
    private const MIN_CHARACTERS = 3;

    /** Estados que siempre son seleccionables, sin importar la fecha. */
    private const ALWAYS_SELECTABLE = ['En ejecución', 'Celebrado', 'Adjudicado'];

    /** Estados cerrados que solo son seleccionables dentro de la ventana configurada. */
    private const SELECTABLE_WHILE_RECENT = ['Terminado', 'Liquidado'];

    /**
     * @return Collection<int, Contract>
     */
    public function handle(Tenant $tenant, string $keyword): Collection
    {
        if (mb_strlen(trim($keyword)) < self::MIN_CHARACTERS) {
            return new Collection;
        }

        $watched = WatchedTerritories::ofActiveOrganizations($tenant->id);
        $windowMonths = (int) (Parameters::current('closed_contract_report_window_months') ?? 12);
        $closedSince = now()->subMonths($windowMonths);

        return Contract::query()
            ->where(function ($query) use ($watched) {
                $query->whereIn('department_code', $watched->departmentCodes())
                    ->orWhereIn('municipality_code', $watched->municipalityCodes());
            })
            ->where(function ($query) use ($closedSince) {
                $query->whereIn('status', self::ALWAYS_SELECTABLE)
                    ->orWhere(function ($query) use ($closedSince) {
                        $query->whereIn('status', self::SELECTABLE_WHILE_RECENT)
                            ->where('end_date', '>=', $closedSince);
                    });
            })
            ->where(function ($query) use ($keyword) {
                $query->where('object', 'ilike', "%{$keyword}%")
                    ->orWhere('contractor_name', 'ilike', "%{$keyword}%")
                    ->orWhere('process_number', 'ilike', "%{$keyword}%");
            })
            ->get();
    }
}
