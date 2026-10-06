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

    /**
     * It. 45c: of the row, only what GovTrace reads. SECOP also publishes the
     * legal representative, the supervisor, their documents and the bank
     * account of the contractor: public, but not needed here (Ley 1581,
     * principio de finalidad). The exception, from the it. 46j: the supervisor's
     * NAME (never their document), the entity's order and the funding sources
     * go in their own columns, for the dossier.
     */
    public const KEPT_FIELDS = [
        'id_contrato', 'referencia_del_contrato', 'nombre_entidad', 'proveedor_adjudicado', 'descripcion_del_proceso',
        'tipo_de_contrato', 'estado_contrato', 'valor_del_contrato', 'fecha_de_firma', 'fecha_de_fin_del_contrato',
        'ciudad', 'departamento', 'urlproceso',
    ];

    /**
     * It. 46j: the six funding sources SECOP II breaks the value into, by the
     * short name the dossier uses.
     */
    private const FUNDING_FIELDS = [
        'pgn' => 'presupuesto_general_de_la_nacion_pgn',
        'sgp' => 'sistema_general_de_participaciones',
        'sgr' => 'sistema_general_de_regal_as',
        'territorial' => 'recursos_propios_alcald_as_gobernaciones_y_resguardos_ind_genas_',
        'credit' => 'recursos_de_credito',
        'own' => 'recursos_propios',
    ];

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
            'supervisor_name' => $this->supervisorName($row),
            'entity_order' => $this->text($row['orden'] ?? null),
            'funding_sources' => $this->fundingSources($row),
            'raw_payload' => array_intersect_key($row, array_flip(self::KEPT_FIELDS)),
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
     * It. 46j: only the supervisor's name, never their document (Ley 1581,
     * principio de finalidad). SECOP II writes "No definido" when it has none.
     */
    private function supervisorName(array $row): ?string
    {
        $name = $this->text($row['nombre_supervisor'] ?? null);

        return $name !== null && Str::lower($name) !== 'no definido' ? $name : null;
    }

    /** @return array<string, int>|null null when the row has none of the six sources. */
    private function fundingSources(array $row): ?array
    {
        $present = array_filter(self::FUNDING_FIELDS, fn (string $field) => isset($row[$field]) && is_numeric($row[$field]));

        if ($present === []) {
            return null;
        }

        return array_map(fn (string $field) => (int) round((float) ($row[$field] ?? 0)), self::FUNDING_FIELDS);
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
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
