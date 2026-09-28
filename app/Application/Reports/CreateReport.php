<?php

namespace App\Application\Reports;

use App\Domain\Configuration\Parameters;
use App\Domain\Contracts\Contract;
use App\Domain\Organization\User;
use App\Domain\Organization\WatchedTerritories;
use App\Domain\Reports\Exceptions\ReportValidationException;
use App\Domain\Reports\Geofence;
use App\Domain\Reports\GpsReading;
use App\Domain\Reports\Report;
use App\Domain\Reports\ReportClassification;
use App\Domain\Reports\ReportComment;
use App\Domain\Reports\SuspiciousCaptureTime;
use App\Domain\Worksites\Worksite;
use App\Domain\Worksites\WorksiteContract;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * US-008: un veedor crea un reporte desde la obra. Corre en el contexto
 * del tenant de su organización (el reporte y la ficha de obra viven en
 * su base; el contrato y el territorio, en la central).
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
        $contract = $this->reportableContract($input->secopContractId, $input->capturedAt);

        // R-AUD-05: el radio que regía cuando se tomó la evidencia.
        $radiusMeters = (int) (Parameters::valueAt('geofence_radius_meters', $input->capturedAt)
            ?? throw new RuntimeException('Falta el parámetro geofence_radius_meters.'));

        return DB::transaction(function () use ($veedor, $input, $receivedAt, $classification, $comment, $reading, $contract, $radiusMeters) {
            $worksite = $this->lockedWorksiteOf($contract);
            $officialLocation = $worksite->location();

            if ($officialLocation === null) {
                $worksite->anchorAt($reading->point);
            } else {
                (new Geofence($officialLocation, $radiusMeters))->assertContains($reading->point);
            }

            return Report::create([
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
            ]);
        });
    }

    /** R-VC-04 + US-016: in the organization's territory, and still reportable when captured. */
    private function reportableContract(string $secopContractId, CarbonInterface $capturedAt): Contract
    {
        $contract = Contract::query()->where('secop_contract_id', $secopContractId)->first()
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

        return Worksite::query()->lockForUpdate()->findOrFail($link->worksite_id);
    }
}
