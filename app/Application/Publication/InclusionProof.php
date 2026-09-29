<?php

namespace App\Application\Publication;

use App\Domain\Reports\Evidence;

/**
 * US-026: la prueba de inclusión de un archivo publicado — lo que el script
 * independiente (tools/verify) necesita para comprobar, sin GovTrace, que
 * el archivo es el que se selló: las hojas del árbol (los archivos del
 * reporte, en orden, y al final el hash del JSON de metadatos), el camino
 * de Merkle del archivo, la raíz y dónde está en la red.
 *
 * Sin el JSON de metadatos: lleva el comentario y las coordenadas exactas,
 * y el mapa público solo da aproximadas (R-PRIV-02). Su hash sí va, porque
 * es una hoja, y no permite recomponerlo: el JSON lleva el seudónimo del
 * veedor, un HMAC con una llave del servidor (D7).
 */
final class InclusionProof
{
    public const FORMAT = 'govtrace-proof/1';

    /** @return array<string, mixed> */
    public static function of(Evidence $evidence): array
    {
        $report = $evidence->report;
        $seal = $report->seal;

        return [
            'format' => self::FORMAT,
            'file' => ['name' => $evidence->downloadName(), 'sha256' => $evidence->sha256],
            'leaves' => [...$report->evidences()->orderBy('leaf_index')->pluck('sha256')->all(), hash('sha256', $seal->metadata_json)],
            'leaf_index' => $evidence->leaf_index,
            'proof' => $evidence->merkle_proof,
            'merkle_root' => $seal->merkle_root,
            'worksite_reference' => $seal->worksite_reference,
            'stellar' => [
                'network_passphrase' => config('stellar.network_passphrase'),
                'contract_id' => $seal->contract_id,
                'tx_hash' => $seal->tx_hash,
                'ledger' => $seal->ledger,
                'sealed_at' => $seal->sealed_at->toImmutable()->utc()->toIso8601String(),
            ],
        ];
    }
}
