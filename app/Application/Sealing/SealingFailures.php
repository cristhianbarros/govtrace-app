<?php

namespace App\Application\Sealing;

use App\Domain\Audit\AuditLog;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealStatus;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\SealReport;
use App\Models\User as SuperAdmin;

/**
 * US-047-MNT: las evidencias en "Falla de Sellado" de todas las
 * organizaciones (US-021: tras el quinto intento no hay más reintentos
 * automáticos), y volverlas a encolar desde el panel global.
 */
final class SealingFailures
{
    /** @return list<array{organization_id: string, organization: string, report_id: int, failed_at: string, attempts: int, last_error: string|null}> */
    public function all(): array
    {
        $failures = [];

        foreach (Tenant::query()->get() as $tenant) {
            $tenant->run(function () use ($tenant, &$failures) {
                foreach (ReportSeal::query()->where('status', SealStatus::Failed)->get() as $seal) {
                    $failures[] = [
                        'organization_id' => $tenant->id,
                        'organization' => $tenant->displayName(),
                        'report_id' => $seal->report_id,
                        'failed_at' => $seal->failed_at->timezone('America/Bogota')->toIso8601String(),
                        'attempts' => $seal->attempts,
                        'last_error' => $seal->last_error,
                    ];
                }
            });
        }

        // La más antigua primero: es la que más lleva esperando.
        usort($failures, fn (array $a, array $b) => [$a['failed_at'], $a['report_id']] <=> [$b['failed_at'], $b['report_id']]);

        return $failures;
    }

    /**
     * Back to "En Cola" with five fresh attempts, one audit entry each. A
     * seal no longer in "Falla de Sellado" is left as it is.
     *
     * @param  list<array{organization_id: string, report_id: int}>  $seals
     * @return int how many went back to the queue
     */
    public function requeue(array $seals, SuperAdmin $actor): int
    {
        $requeued = [];

        foreach (collect($seals)->groupBy('organization_id') as $organizationId => $ofOrganization) {
            Tenant::query()->findOrFail($organizationId)->run(function () use ($organizationId, $ofOrganization, $actor, &$requeued) {
                $failed = ReportSeal::query()
                    ->where('status', SealStatus::Failed)
                    ->whereIn('report_id', $ofOrganization->pluck('report_id'))
                    ->get();

                foreach ($failed as $seal) {
                    $before = ['report_id' => $seal->report_id, 'status' => $seal->status->value, 'attempts' => $seal->attempts, 'last_error' => $seal->last_error];
                    $seal->requeue();

                    AuditLog::record(
                        action: 'seal.requeued',
                        organizationId: $organizationId,
                        actorType: 'super_admin',
                        actorId: (string) $actor->getKey(),
                        actorName: $actor->name,
                        before: $before,
                        after: ['report_id' => $seal->report_id, 'status' => $seal->status->value, 'attempts' => $seal->attempts],
                    );

                    $requeued[] = [$organizationId, $seal->report_id];
                }
            });
        }

        foreach ($requeued as [$organizationId, $reportId]) {
            SealReport::dispatch($organizationId, $reportId);
        }

        return count($requeued);
    }
}
