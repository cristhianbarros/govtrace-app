<?php

namespace App\Application\Reports;

use App\Application\Publication\WorksiteLabels;
use App\Domain\Reports\Evidence;
use App\Domain\Reports\Report;
use App\Domain\Sealing\VeedorPseudonym;

/**
 * US-050-RPT: las obras y evidencias de la organización en CSV, para su
 * Administrador — una fila por archivo de evidencia, cualquiera sea su
 * estado editorial. El veedor va con su seudónimo (R-PRIV-03), nunca su
 * nombre ni su correo. Solo esta organización: los datos viven en su base.
 */
final class EvidenceExport
{
    public const COLUMNS = ['obra', 'contrato', 'municipio', 'fecha', 'clasificación', 'estado editorial', 'hash de la evidencia', 'comentario', 'seudónimo del veedor'];

    private const TIMEZONE = 'America/Bogota';

    /** @return iterable<list<string>> one row per evidence file, oldest report first */
    public function rows(): iterable
    {
        [$labels, $pseudonyms] = [[], []];

        foreach (Report::query()->with(['evidences' => fn ($query) => $query->orderBy('id'), 'user'])->orderBy('id')->lazyById(200) as $report) {
            $labels[$report->worksite_id] ??= WorksiteLabels::of([$report->worksite_id])[$report->worksite_id];
            $pseudonyms[$report->user_id] ??= VeedorPseudonym::of($report->user);

            /** @var Evidence $evidence */
            foreach ($report->evidences as $evidence) {
                yield [
                    $labels[$report->worksite_id]['name'],
                    $labels[$report->worksite_id]['contracts'],
                    $labels[$report->worksite_id]['municipalities'],
                    $report->captured_at->timezone(self::TIMEZONE)->format('Y-m-d H:i'),
                    $report->classification->value,
                    $report->editorial_status->label(),
                    $evidence->sha256,
                    (string) $report->comment,
                    $pseudonyms[$report->user_id],
                ];
            }
        }
    }
}
