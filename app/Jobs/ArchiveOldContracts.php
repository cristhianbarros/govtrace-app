<?php

namespace App\Jobs;

use App\Domain\Contracts\ContractArchive;
use App\Domain\Worksites\WorksiteContract;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * US-048-MNT: el primer día de cada mes, archiva los contratos cerrados
 * hace más de 5 años (R-MNT-04) que ninguna organización — activa o
 * suspendida — tiene en una ficha de obra. Una ficha es donde viven sus
 * evidencias; una sin reportes también lo mantiene, porque su vista
 * pública lo muestra.
 */
class ArchiveOldContracts implements ShouldQueue
{
    use Dispatchable, Queueable;

    private const CHUNK = 500;

    public function handle(): void
    {
        $inWorksites = [];
        foreach (Tenant::query()->get() as $tenant) {
            $tenant->run(function () use (&$inWorksites) {
                foreach (WorksiteContract::query()->pluck('secop_contract_id') as $secopContractId) {
                    $inWorksites[$secopContractId] = true;
                }
            });
        }

        ContractArchive::candidates(today())
            ->pluck('secop_contract_id')
            ->reject(fn (string $secopContractId) => isset($inWorksites[$secopContractId]))
            ->chunk(self::CHUNK)
            ->each(fn ($secopContractIds) => ContractArchive::archive($secopContractIds));
    }
}
