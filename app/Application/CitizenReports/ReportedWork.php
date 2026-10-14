<?php

namespace App\Application\CitizenReports;

use App\Application\Worksites\WorksiteOfContract;
use App\Domain\Contracts\Contract;
use App\Domain\Organization\WatchedTerritories;
use App\Domain\Worksites\Worksite;
use Illuminate\Database\Eloquent\Builder;

/**
 * US-059-LEG: what a citizen informs the veeduría about — a worksite sheet
 * or, since it. 58, a contract of its territory that has no sheet yet,
 * because no veedor has reported it. Either way, with the contract a place
 * proposed for it is measured against: the sheet's first one.
 *
 * Inside the organization.
 */
final class ReportedWork
{
    private function __construct(
        public readonly ?Contract $contract,
        public readonly ?Worksite $worksite,
    ) {}

    public static function sheet(Worksite $worksite): self
    {
        $contract = Contract::query()
            ->whereIn('secop_contract_id', $worksite->contracts()->pluck('secop_contract_id'))
            ->orderBy('secop_contract_id')
            ->first();

        return new self($contract, $worksite);
    }

    /** One of the contracts the veeduría watches and may still be reported (R-SEC-07); refused otherwise. */
    public static function contract(string $secopContractId): self
    {
        $contract = self::reportableContracts()->where('secop_contract_id', $secopContractId)->first()
            ?? throw CitizenReportRefused::notWatched();
        $sheet = WorksiteOfContract::existingIdFor($secopContractId);

        return new self($contract, $sheet !== null ? Worksite::query()->find($sheet) : null);
    }

    /** The contracts of the territory of the organization that its veedores, and its citizens, may report on today. */
    public static function reportableContracts(): Builder
    {
        return Contract::query()
            ->inTerritory(WatchedTerritories::ofActiveOrganizations(tenant()->getTenantKey()))
            ->reportableAt(now());
    }

    public function isLocated(): bool
    {
        return $this->worksite?->location() !== null;
    }

    /** The sheet the report goes to: created, without location, if the contract had none. */
    public function sheetForReport(): Worksite
    {
        return $this->worksite ?? Worksite::query()->findOrFail(WorksiteOfContract::idFor($this->contract->secop_contract_id));
    }
}
