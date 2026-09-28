<?php

namespace App\Application\Contracts;

use App\Domain\Contracts\Contract;
use App\Domain\Geography\MunicipalityMatcher;
use App\Domain\Organization\WatchedTerritories;
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

        $municipality = $this->matcher->matchSecopLocation((string) ($row['departamento'] ?? ''), (string) ($row['ciudad'] ?? ''));

        if (! $municipality) {
            return SecopRowOutcome::Unmatched;
        }

        if ($watched && ! $watched->covers($municipality)) {
            return SecopRowOutcome::OutOfTerritory;
        }

        $secopStatus = trim((string) ($row['estado_contrato'] ?? ''));
        $status = in_array(Str::lower($secopStatus), self::CANCELLED_STATUSES, true) ? 'cancelled' : ($secopStatus ?: 'Desconocido');

        $contract = Contract::fromSecop(fn () => Contract::updateOrCreate(
            ['secop_contract_id' => $row['id_contrato']],
            [
                'process_number' => $row['referencia_del_contrato'] ?? null,
                'entity_name' => $row['nombre_entidad'] ?? '',
                'contractor_name' => $row['proveedor_adjudicado'] ?? null,
                'object' => $row['descripcion_del_proceso'] ?? null,
                'contract_type' => $row['tipo_de_contrato'],
                'value' => $row['valor_del_contrato'] ?? null,
                'signed_at' => $row['fecha_de_firma'] ?? null,
                'end_date' => $row['fecha_de_fin_del_contrato'] ?? null,
                'status' => $status,
                'department_code' => $municipality->department_code,
                'municipality_code' => $municipality->code,
                'secop_url' => $row['urlproceso']['url'] ?? null,
                'raw_payload' => $row,
            ],
        ));

        return $contract->wasRecentlyCreated ? SecopRowOutcome::Inserted : SecopRowOutcome::Updated;
    }
}
