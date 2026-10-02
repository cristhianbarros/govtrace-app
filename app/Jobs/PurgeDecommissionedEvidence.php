<?php

namespace App\Jobs;

use App\Application\Organization\RegistrationDocuments;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\OrganizationStatus;
use App\Domain\Reports\Evidence;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;

/**
 * US-003b, retención: 5 años después de la baja de una organización, cada
 * día se borran sus archivos de evidencia (fotos y PDF). Solo los
 * archivos: sus sellos en Stellar, sus hashes y sus pruebas de inclusión
 * se conservan, así que una copia que alguien guardó sigue verificable.
 * Una sola vez por organización, y queda en el log de auditoría.
 */
class PurgeDecommissionedEvidence implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        $due = Tenant::query()
            ->where('status', OrganizationStatus::Decommissioned->value)
            ->whereNull('evidence_files_purged_at')
            ->where('decommissioned_at', '<=', now()->subYears(Tenant::EVIDENCE_RETENTION_YEARS))
            ->get();

        foreach ($due as $tenant) {
            $deleted = $tenant->run(function () {
                $deleted = 0;
                Evidence::query()->select(['id', 'storage_path'])->chunkById(500, function ($files) use (&$deleted) {
                    Storage::disk('evidencias')->delete($files->pluck('storage_path')->all());
                    $deleted += $files->count();
                });

                return $deleted;
            });

            // It. 46b: el PDF de inscripción de su solicitud de alta, que quedó con la organización.
            RegistrationDocuments::forget($tenant->registration_document_path);

            $tenant->update(['evidence_files_purged_at' => now(), 'registration_document_path' => null]);

            AuditLog::record(
                action: 'organization.evidence_files_purged',
                organizationId: $tenant->id,
                actorType: 'system',
                after: ['files_deleted' => $deleted],
            );
        }
    }
}
