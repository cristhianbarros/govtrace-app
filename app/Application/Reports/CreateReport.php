<?php

namespace App\Application\Reports;

use App\Application\Sealing\QueueReportForSealing;
use App\Domain\Configuration\Parameters;
use App\Domain\Contracts\Contract;
use App\Domain\Contracts\ContractArchive;
use App\Domain\Organization\User;
use App\Domain\Organization\WatchedTerritories;
use App\Domain\Reports\Evidence;
use App\Domain\Reports\EvidenceSet;
use App\Domain\Reports\EvidenceUpload;
use App\Domain\Reports\Exceptions\ReportValidationException;
use App\Domain\Reports\Geofence;
use App\Domain\Reports\GpsReading;
use App\Domain\Reports\Report;
use App\Domain\Reports\ReportClassification;
use App\Domain\Reports\ReportComment;
use App\Domain\Reports\SuspiciousCaptureTime;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealStatus;
use App\Domain\Worksites\Worksite;
use App\Domain\Worksites\WorksiteContract;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * US-008: un veedor crea un reporte desde la obra. Corre en el contexto
 * del tenant de su organización (el reporte y la ficha de obra viven en
 * su base; el contrato y el territorio, en la central).
 *
 * Los archivos (US-009) se verifican antes de tocar la base: si el hash
 * que el servidor recalcula no coincide con el del teléfono, no se guarda
 * nada. Si coinciden, se guardan byte a byte en el disco "evidencias", y
 * el reporte queda "Recibida" y luego "En Cola" para el sellado (US-020b).
 *
 * First-Touch (R-GEO-01) con bloqueo atómico: la ficha se lee con
 * SELECT … FOR UPDATE, así que de dos veedores enviando a la vez el
 * primer reporte, el segundo espera a que el primero confirme y se valida
 * contra la ubicación que este fijó. Si la ficha ni siquiera existía,
 * la carrera la resuelve el índice único de worksite_contracts.
 */
class CreateReport
{
    public function handle(User $veedor, NewReport $input): Report
    {
        $receivedAt = now();

        $classification = ReportClassification::fromInput($input->classification);
        $comment = ReportComment::fromInput($input->comment);
        $reading = GpsReading::fromDevice($input->latitude, $input->longitude, $input->accuracyMeters);
        $evidenceSet = EvidenceSet::fromUploads($input->files);
        $contract = $this->reportableContract($input->secopContractId, $input->capturedAt);

        // R-AUD-05: el radio que regía cuando se tomó la evidencia.
        $radiusMeters = (int) (Parameters::valueAt('geofence_radius_meters', $input->capturedAt)
            ?? throw new RuntimeException('Falta el parámetro geofence_radius_meters.'));

        $report = DB::transaction(function () use ($veedor, $input, $receivedAt, $classification, $comment, $reading, $evidenceSet, $contract, $radiusMeters) {
            $worksite = $this->lockedWorksiteOf($contract);
            $officialLocation = $worksite->location();

            // It. 45f: lo que la Bandeja dirá de su ubicación, sin las coordenadas del veedor.
            $anchored = $officialLocation === null;
            if ($anchored) {
                $worksite->anchorAt($reading->point);
                $distanceMeters = 0;
            } else {
                $distanceMeters = (int) round((new Geofence($officialLocation, $radiusMeters))->assertContains($reading->point));
            }

            $report = Report::create([
                'user_id' => $veedor->id,
                'worksite_id' => $worksite->id,
                'classification' => $classification,
                'comment' => $comment?->text,
                'latitude' => $reading->point->latitude,
                'longitude' => $reading->point->longitude,
                'accuracy_meters' => $reading->accuracyMeters,
                'geofence_radius_meters' => $radiusMeters,
                'captured_at' => $input->capturedAt,
                'received_at' => $receivedAt,
                'suspicious_capture_time' => SuspiciousCaptureTime::applies($input->capturedAt, $receivedAt),
                'anchored_worksite' => $anchored,
                'distance_to_worksite_meters' => $distanceMeters,
            ]);

            $this->storeEvidences($report, $evidenceSet);

            // US-020b: "Recibida", con la misma transacción que el reporte.
            ReportSeal::create(['report_id' => $report->id, 'status' => SealStatus::Received, 'received_at' => $receivedAt]);

            return $report;
        });

        // Ya confirmado: "En Cola" y el trabajo de sellado despachado.
        (new QueueReportForSealing)->handle($report);

        return $report;
    }

    /**
     * Byte for byte, as the phone sent them (R-PRIV-05: nothing is blurred
     * or re-encoded — what's stored is what gets sealed). If anything fails,
     * the objects already written are removed and the transaction rolls
     * the report back.
     */
    private function storeEvidences(Report $report, EvidenceSet $evidenceSet): void
    {
        $disk = Storage::disk('evidencias');
        $storedPaths = [];

        try {
            foreach ($evidenceSet->uploads as $upload) {
                $path = $this->storagePath($report, $upload);
                $stream = fopen($upload->path, 'rb');

                if (! $disk->put($path, $stream)) {
                    throw new RuntimeException("No se pudo guardar la evidencia {$path} en el almacenamiento.");
                }

                $storedPaths[] = $path;

                Evidence::create([
                    'report_id' => $report->id,
                    'kind' => $upload->kind()->value,
                    'mime_type' => $upload->mimeType,
                    'size_bytes' => $upload->sizeBytes,
                    'sha256' => $upload->serverSha256(),
                    'storage_path' => $path,
                ]);
            }
        } catch (Throwable $e) {
            $disk->delete($storedPaths);

            throw $e;
        }
    }

    /** {organización}/reports/{reporte}/{sha256}.{jpg|pdf} — each organization under its own prefix. */
    private function storagePath(Report $report, EvidenceUpload $upload): string
    {
        return sprintf('%s/reports/%d/%s.%s', tenant()->getTenantKey(), $report->id, $upload->serverSha256(), $upload->kind()->extension());
    }

    /** R-VC-04 + US-016: in the organization's territory, and still reportable when captured. */
    private function reportableContract(string $secopContractId, CarbonInterface $capturedAt): Contract
    {
        $contract = Contract::query()->where('secop_contract_id', $secopContractId)->first()
            // US-048-MNT: si estaba archivado, vuelve, y el reporte sigue las reglas de siempre.
            ?? ContractArchive::restore($secopContractId)
            ?? throw ReportValidationException::contractNotFound();

        $inTerritory = Contract::query()
            ->whereKey($contract->getKey())
            ->inTerritory(WatchedTerritories::ofActiveOrganizations(tenant()->getTenantKey()))
            ->exists();

        if (! $inTerritory) {
            throw ReportValidationException::contractOutsideTerritory();
        }

        if (! Contract::query()->whereKey($contract->getKey())->reportableAt($capturedAt)->exists()) {
            throw ReportValidationException::contractNotReportable();
        }

        return $contract;
    }

    /** The worksite grouping the contract, locked until the transaction ends; created if it doesn't exist yet. */
    private function lockedWorksiteOf(Contract $contract): Worksite
    {
        $link = WorksiteContract::query()->where('secop_contract_id', $contract->secop_contract_id)->first();

        if ($link === null) {
            try {
                // Savepoint: si falla, solo se deshace esta creación.
                return DB::transaction(function () use ($contract) {
                    $worksite = Worksite::create();
                    $worksite->contracts()->create(['secop_contract_id' => $contract->secop_contract_id]);

                    return $worksite;
                });
            } catch (UniqueConstraintViolationException) {
                // Otro veedor creó la ficha de este contrato al mismo tiempo: se usa la suya.
                $link = WorksiteContract::query()->where('secop_contract_id', $contract->secop_contract_id)->firstOrFail();
            }
        }

        // Si mientras tanto el Administrador la fundió en otra ficha (US-045-INT), la de ahora.
        return Worksite::query()->lockForUpdate()->find($link->worksite_id)
            ?? $this->lockedWorksiteOf($contract);
    }
}
