<?php

namespace App\Application\Publication;

use App\Domain\Reports\Report;
use App\Domain\Sealing\VeedorPseudonym;

/**
 * US-052-RPT: los datos abiertos de la organización — un registro por
 * evidencia publicada, con su sello en Stellar, para auditarla y
 * reutilizarla sin GovTrace. Las coordenadas van aproximadas (R-PRIV-02,
 * ~110 m) y el veedor con su seudónimo (R-PRIV-03): nunca quién es. Ni el
 * JSON sellado, que tiene la ubicación exacta.
 */
final class OpenData
{
    public const FIELDS = [
        'reporte', 'obra', 'contrato', 'municipio', 'fecha', 'clasificacion', 'latitud', 'longitud',
        'raiz_merkle', 'tx_id', 'ledger', 'contrato_de_sellado', 'comentario', 'seudonimo_veedor',
    ];

    private const TIMEZONE = 'America/Bogota';

    /** @return list<array<string, mixed>> */
    public function records(): array
    {
        $reports = Report::query()->onPublicMap()->with(['seal', 'user'])->orderBy('id')->get();
        $labels = WorksiteLabels::of($reports->pluck('worksite_id')->unique()->values()->all());
        $pseudonyms = [];

        return $reports->map(function (Report $report) use ($labels, &$pseudonyms) {
            $place = $report->location()->approximate();
            $pseudonyms[$report->user_id] ??= VeedorPseudonym::of($report->user);

            return [
                'reporte' => $report->id,
                'obra' => $labels[$report->worksite_id]['name'],
                'contrato' => $labels[$report->worksite_id]['contracts'],
                'municipio' => $labels[$report->worksite_id]['municipalities'],
                'fecha' => $report->captured_at->timezone(self::TIMEZONE)->toIso8601String(),
                'clasificacion' => $report->classification->value,
                'latitud' => $place->latitude,
                'longitud' => $place->longitude,
                'raiz_merkle' => $report->seal->merkle_root,
                'tx_id' => $report->seal->tx_hash,
                'ledger' => $report->seal->ledger,
                'contrato_de_sellado' => $report->seal->contract_id,
                'comentario' => $report->comment,
                'seudonimo_veedor' => $pseudonyms[$report->user_id],
            ];
        })->all();
    }
}
