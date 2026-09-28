<?php

namespace App\Application\Sealing;

use App\Domain\Sealing\MerkleTree;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealedMetadata;
use App\Domain\Sealing\VeedorPseudonym;
use Illuminate\Support\Facades\DB;

/**
 * US-020b / R-SEC-06: arma lo que se sella de un reporte, a partir de los
 * hashes que el propio servidor verificó (R-HASH-01) — cualquier raíz que
 * haya mandado el teléfono se ignora. Las hojas son los archivos, en orden,
 * y al final el hash del JSON de metadatos; cada archivo guarda su prueba.
 */
class PrepareReportSeal
{
    public function handle(ReportSeal $seal): void
    {
        // Un reintento sella exactamente lo mismo que la primera vez.
        if ($seal->merkle_root !== null) {
            return;
        }

        $report = $seal->report()->with(['evidences' => fn ($query) => $query->orderBy('id'), 'user'])->firstOrFail();
        $metadata = SealedMetadata::forReport($report, VeedorPseudonym::of($report->user));
        $tree = MerkleTree::fromLeaves([...$report->evidences->pluck('sha256')->all(), $metadata->sha256()]);

        DB::transaction(function () use ($seal, $report, $metadata, $tree) {
            foreach ($report->evidences as $index => $evidence) {
                $evidence->update(['leaf_index' => $index, 'merkle_proof' => $tree->proof($index)]);
            }

            $seal->update([
                'merkle_root' => $tree->root(),
                'metadata_json' => $metadata->json,
                // R-BLK-02: única entre organizaciones y sin datos legibles.
                'worksite_reference' => hash('sha256', tenant()->getTenantKey().':'.$report->worksite_id),
            ]);
        });
    }
}
