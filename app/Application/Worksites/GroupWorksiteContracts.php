<?php

namespace App\Application\Worksites;

use App\Domain\Audit\AuditLog;
use App\Domain\Contracts\Contract;
use App\Domain\Organization\User;
use App\Domain\Organization\WatchedTerritories;
use App\Domain\Reports\Report;
use App\Domain\Worksites\Exceptions\WorksiteGroupingRejected;
use App\Domain\Worksites\Worksite;
use App\Domain\Worksites\WorksiteContract;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * US-045-INT (R-INT-05): el Administrador agrupa dos o más contratos de obra
 * de su territorio en una ficha, para que una obra con varias fases o
 * reinicios se vea y se reporte como una sola.
 *
 * Un reporte nunca cambia de ficha: su obra es parte de lo que envió el
 * veedor (R-TA-02) y quedó sellada en la red. Así que:
 * - la ficha que ya tiene reportes recibe a los demás contratos y conserva
 *   su ubicación;
 * - las fichas que se quedan sin contratos, que no tienen reportes, sobran;
 * - si dos de los contratos tienen reportes en fichas distintas, no se
 *   agrupan.
 *
 * Contra un veedor que reporta al mismo tiempo: las fichas se bloquean
 * (como al crear un reporte), y el vínculo de un contrato que aún no tenía
 * ficha se inserta, no se sobrescribe: si el reporte lo creó primero, la
 * agrupación falla y se reintenta.
 */
class GroupWorksiteContracts
{
    public const NAME_MAX_LENGTH = 150;

    /** @param  list<string>  $secopContractIds */
    public function handle(User $administrator, string $name, array $secopContractIds): Worksite
    {
        $name = trim($name);
        if ($name === '') {
            throw WorksiteGroupingRejected::nameRequired();
        }
        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw WorksiteGroupingRejected::nameTooLong(self::NAME_MAX_LENGTH);
        }

        $secopContractIds = array_values(array_unique($secopContractIds));
        if (count($secopContractIds) < 2) {
            throw WorksiteGroupingRejected::needsTwoContracts();
        }
        $this->ensureInTerritory($secopContractIds);

        try {
            // El log vive en la base central; si falla, las fichas no cambian.
            return DB::transaction(fn () => $this->group($administrator, $name, $secopContractIds));
        } catch (UniqueConstraintViolationException) {
            throw WorksiteGroupingRejected::reportedMeanwhile();
        }
    }

    /** @param  list<string>  $secopContractIds */
    private function ensureInTerritory(array $secopContractIds): void
    {
        $watched = WatchedTerritories::ofActiveOrganizations(tenant()->getTenantKey());

        foreach ($secopContractIds as $secopContractId) {
            $contract = Contract::query()->where('secop_contract_id', $secopContractId)->first()
                ?? throw WorksiteGroupingRejected::unknownContract($secopContractId);

            if (! Contract::query()->whereKey($contract->getKey())->inTerritory($watched)->exists()) {
                throw WorksiteGroupingRejected::outsideTerritory($secopContractId);
            }
        }
    }

    /** @param  list<string>  $secopContractIds */
    private function group(User $administrator, string $name, array $secopContractIds): Worksite
    {
        $links = WorksiteContract::query()->whereIn('secop_contract_id', $secopContractIds)->lockForUpdate()->get();
        $current = Worksite::query()->whereKey($links->pluck('worksite_id')->unique())->orderBy('id')->lockForUpdate()->get();

        // Leídos tras el bloqueo: un reporte que se estaba creando ya cuenta.
        $withReports = Report::query()->whereIn('worksite_id', $current->modelKeys())->distinct()->pluck('worksite_id');
        if ($withReports->count() > 1) {
            throw WorksiteGroupingRejected::reportsInSeveralWorksites(
                $links->whereIn('worksite_id', $withReports)->pluck('secop_contract_id')->sort()->values()->all(),
            );
        }

        // La que tiene reportes; si ninguna, la primera ya ubicada; si no, la más antigua; o una nueva.
        $target = $current->firstWhere('id', $withReports->first())
            ?? $current->first(fn (Worksite $worksite) => $worksite->location() !== null)
            ?? $current->first()
            ?? new Worksite;
        $target->fill(['name' => $name])->save();

        foreach ($secopContractIds as $secopContractId) {
            $link = $links->firstWhere('secop_contract_id', $secopContractId);
            $link
                ? $link->update(['worksite_id' => $target->id])
                : $target->contracts()->create(['secop_contract_id' => $secopContractId]);
        }

        $current->reject(fn (Worksite $worksite) => $worksite->is($target))
            ->each(fn (Worksite $emptied) => $emptied->contracts()->exists() ? null : $emptied->delete());

        $grouped = $target->contracts()->orderBy('secop_contract_id')->pluck('secop_contract_id')->all();

        AuditLog::record(
            action: 'worksite.contracts_grouped',
            organizationId: tenant()->getTenantKey(),
            actorType: 'organization_admin',
            actorId: (string) $administrator->id,
            actorName: $administrator->name,
            before: ['worksites' => $current->map(fn (Worksite $worksite) => [
                'worksite_id' => $worksite->id,
                'secop_contract_ids' => $links->where('worksite_id', $worksite->id)->pluck('secop_contract_id')->sort()->values()->all(),
            ])->all()],
            after: ['worksite_id' => $target->id, 'name' => $name, 'secop_contract_ids' => $grouped],
        );

        return $target;
    }
}
