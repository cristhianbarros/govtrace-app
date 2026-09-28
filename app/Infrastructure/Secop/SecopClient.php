<?php

namespace App\Infrastructure\Secop;

use Generator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The public Socrata (SODA) API for "SECOP II - Contratos Electrónicos"
 * (datos.gov.co, dataset jbjy-vk9h — Colombia Compra Eficiente).
 *
 * Filters on the SECOP side by contract type and DEPARTMENT, never by
 * city: SECOP spells cities loosely ("Calarca", "Cali", "No Definido"),
 * so an exact city filter there silently misses contracts. The city is
 * matched on our side, with DIVIPOLA normalization (MunicipalityMatcher),
 * and whatever falls outside the watched territory is ignored unsaved.
 */
class SecopClient
{
    private const ENDPOINT = 'https://www.datos.gov.co/resource/jbjy-vk9h.json';

    public function __construct(private readonly int $pageSize = 1000) {}

    /**
     * Every works contract of the department, page by page.
     *
     * @param  string  $departmentName  as SECOP spells it, see Department::secopName()
     * @return Generator<int, array<string, mixed>>
     */
    public function fetchWorksContracts(string $departmentName): Generator
    {
        $where = sprintf("tipo_de_contrato='Obra' AND upper(departamento)=upper('%s')", $this->escape($departmentName));

        for ($offset = 0; ; $offset += $this->pageSize) {
            $page = Http::timeout(30)
                ->get(self::ENDPOINT, [
                    '$where' => $where,
                    '$order' => ':id', // SODA only pages reliably over a stable order
                    '$limit' => $this->pageSize,
                    '$offset' => $offset,
                ])
                ->throw() // the job's tries/backoff do the retrying, not the HTTP client
                ->json() ?? [];

            foreach ($page as $row) {
                yield $row;
            }

            if (count($page) < $this->pageSize) {
                return;
            }
        }
    }

    private function escape(string $value): string
    {
        // SoQL usa comillas simples dobladas para escapar, no barras.
        return Str::replace("'", "''", $value);
    }
}
