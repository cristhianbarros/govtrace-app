<?php

namespace App\Jobs;

use App\Domain\Contracts\Contract;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * US-034: cada día, cada organización ACTIVA revisa sus propias fichas
 * de obra y marca "en riesgo" las que su contrato ya venció y SECOP
 * sigue reportando "En ejecución". El estado calculado vive en la ficha
 * de obra — el contrato de SECOP nunca se toca (R-SEC-01).
 *
 * Recorre las organizaciones una por una (Tenant::run()) porque cada
 * ficha de obra vive en la base de su propio tenant; una organización
 * suspendida no se recalcula (mismo criterio que SyncSecopContracts).
 */
class CalculateWorksitesAtRisk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Tenant::query()->where('status', 'active')->get()
            ->each(fn (Tenant $tenant) => $tenant->run(fn () => $this->recalculateForCurrentTenant()));
    }

    private function recalculateForCurrentTenant(): void
    {
        Worksite::query()->get()->each(function (Worksite $worksite) {
            $contract = Contract::query()->where('secop_contract_id', $worksite->secop_contract_id)->first();

            $atRisk = $contract !== null
                && $contract->status === 'En ejecución'
                && $contract->end_date !== null
                && $contract->end_date->isPast();

            if ($worksite->at_risk !== $atRisk) {
                $worksite->update(['at_risk' => $atRisk]);
            }
        });
    }
}
