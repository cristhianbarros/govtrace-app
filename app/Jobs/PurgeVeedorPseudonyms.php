<?php

namespace App\Jobs;

use App\Domain\Audit\AuditLog;
use App\Domain\Sealing\VeedorPseudonym;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

/**
 * R-MNT-03: la tabla seudónimo→veedor (D7) se conserva 5 años. Cada día,
 * en cada organización — también suspendida o dada de baja —, se borra la
 * fila de un veedor que lleva 5 años sin reportar. Sus reportes y sellos
 * no cambian: el JSON sellado ya llevaba el seudónimo, nunca su ID. Queda
 * en el log de auditoría cuántas se borraron.
 */
class PurgeVeedorPseudonyms implements ShouldQueue
{
    use Dispatchable, Queueable;

    public const RETENTION_YEARS = 5;

    public function handle(): void
    {
        $limit = now()->subYears(self::RETENTION_YEARS);

        foreach (Tenant::query()->get() as $tenant) {
            $deleted = $tenant->run(fn () => VeedorPseudonym::query()
                ->whereNotExists(fn ($reports) => $reports->select(DB::raw(1))
                    ->from('reports')
                    ->whereColumn('reports.user_id', 'veedor_pseudonyms.user_id')
                    ->where('reports.received_at', '>', $limit))
                ->delete());

            if ($deleted > 0) {
                AuditLog::record(
                    action: 'organization.pseudonyms_purged',
                    organizationId: $tenant->id,
                    actorType: 'system',
                    after: ['pseudonyms_deleted' => $deleted],
                );
            }
        }
    }
}
