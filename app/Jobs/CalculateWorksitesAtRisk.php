<?php

namespace App\Jobs;

use App\Domain\Contracts\Contract;
use App\Domain\Contracts\SecopContractStatus;
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
 * sigue reportando en ejecución (R-SEC-07). El estado calculado vive en la ficha
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
        Worksite::query()->with('contracts')->get()->each(function (Worksite $worksite) {
            // Una ficha puede agrupar varios contratos (R-INT-05, p. ej.
            // las fases de una obra): basta con que uno siga en ejecución
            // según SECOP ("En ejecución" o "Modificado", R-SEC-07) con su
            // fecha de terminación ya pasada.
            $atRisk = Contract::query()
                ->whereIn('secop_contract_id', $worksite->contracts->pluck('secop_contract_id'))
                ->statusIn(SecopContractStatus::IN_EXECUTION)
                ->whereDate('end_date', '<', today())
                ->exists();

            if ($worksite->at_risk !== $atRisk) {
                $worksite->update(['at_risk' => $atRisk]);
            }
        });
    }
}
