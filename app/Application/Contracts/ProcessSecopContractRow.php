<?php

namespace App\Application\Contracts;

use App\Domain\Contracts\Contract;
use App\Domain\Contracts\ContractArchive;
use App\Domain\Geography\MunicipalityMatcher;
use App\Domain\Organization\WatchedTerritories;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * US-013 (emparejamiento) + US-032 (filtro de tipo) + US-033 (upsert sin
 * duplicados). One raw SECOP row in, one outcome out — the loop over
 * departments and pages lives in the SyncSecopContracts job; this class
 * only knows about a single row.
 */
class ProcessSecopContractRow
{
    /**
     * "Anulado" is the word the discovery used (US-033); the SODA API
     * actually publishes "Cancelado". Both mean the same to us.
     */
    private const CANCELLED_STATUSES = ['anulado', 'cancelado'];

    public function __construct(
        private readonly MunicipalityMatcher $matcher = new MunicipalityMatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $row  Un registro tal como lo entrega la API SODA de SECOP II.
     * @param  WatchedTerritories|null  $watched  null = no territory filter (a row processed on its own).
     */
    public function handle(array $row, ?WatchedTerritories $watched = null): SecopRowOutcome
    {
        // Defensivo: el cliente ya filtra tipo_de_contrato='Obra' en el
        // $where, pero US-032 se prueba pasando filas sueltas, sin pasar
        // por el cliente HTTP.
        if (($row['tipo_de_contrato'] ?? null) !== 'Obra') {
            return SecopRowOutcome::NotWorks;
        }

        $departamento = (string) ($row['departamento'] ?? '');
        $ciudad = (string) ($row['ciudad'] ?? '');

        $department = $this->matcher->matchDepartment($departamento);

        if (! $department) {
            return SecopRowOutcome::Unmatched;
        }

        $municipality = $this->matcher->matchSecopLocation($departamento, $ciudad);

        if ($municipality) {
            $departmentCode = $municipality->department_code;
            $municipalityCode = $municipality->code;
            $covered = ! $watched || $watched->covers($municipality);
        } elseif ($this->matcher->normalize($ciudad) === 'NO DEFINIDO') {
            // US-015/US-016 (it. 8): "un departamento incluye la
            // Gobernación y todos sus municipios" — SECOP publica los
            // contratos de la Gobernación con ciudad "No Definido".
            $departmentCode = $department->code;
            $municipalityCode = null;
            $covered = ! $watched || $watched->coversDepartmentCode($department->code);
        } else {
            return SecopRowOutcome::Unmatched;
        }

        if (! $covered) {
            return SecopRowOutcome::OutOfTerritory;
        }

        $secopStatus = trim((string) ($row['estado_contrato'] ?? ''));
        $status = in_array(Str::lower($secopStatus), self::CANCELLED_STATUSES, true) ? 'cancelled' : ($secopStatus ?: 'Desconocido');

        $attributes = [
            'process_number' => $row['referencia_del_contrato'] ?? null,
            'entity_name' => $row['nombre_entidad'] ?? '',
            'contractor_name' => $row['proveedor_adjudicado'] ?? null,
            'object' => $row['descripcion_del_proceso'] ?? null,
            'contract_type' => $row['tipo_de_contrato'],
            'value' => $row['valor_del_contrato'] ?? null,
            'signed_at' => $row['fecha_de_firma'] ?? null,
            'end_date' => $row['fecha_de_fin_del_contrato'] ?? null,
            'status' => $status,
            'department_code' => $departmentCode,
            'municipality_code' => $municipalityCode,
            'secop_url' => $row['urlproceso']['url'] ?? null,
            'raw_payload' => $row,
            'cancelled_at' => $status === 'cancelled' ? $this->cancelledSince($row['id_contrato']) : null,
        ];

        // US-048-MNT: uno archivado sigue archivado, al día; vuelve si SECOP lo reabre.
        if (ContractArchive::isArchived($row['id_contrato'])) {
            $endDate = $attributes['end_date'] ? Carbon::parse($attributes['end_date']) : null;

            if (ContractArchive::isArchivable($status, $endDate, today())) {
                ContractArchive::refresh($row['id_contrato'], $attributes);

                return SecopRowOutcome::Updated;
            }

            ContractArchive::restore($row['id_contrato']);
        }

        $contract = Contract::fromSecop(fn () => Contract::updateOrCreate(['secop_contract_id' => $row['id_contrato']], $attributes));

        return $contract->wasRecentlyCreated ? SecopRowOutcome::Inserted : SecopRowOutcome::Updated;
    }

    /**
     * US-018: when GovTrace saw it annulled — now, if this sync is the one
     * that sees it change; the moment already known, if it was annulled
     * before; none (annulled since always) if it arrives annulled, or was
     * annulled before this column existed.
     */
    private function cancelledSince(string $secopContractId): ?CarbonInterface
    {
        $known = Contract::query()->where('secop_contract_id', $secopContractId)->first(['status', 'cancelled_at']);

        return match (true) {
            $known === null => null,
            $known->status === 'cancelled' => $known->cancelled_at,
            default => now(),
        };
    }
}
