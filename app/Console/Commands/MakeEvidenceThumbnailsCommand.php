<?php

namespace App\Console\Commands;

use App\Application\Publication\EvidenceThumbnails;
use App\Domain\Reports\Evidence;
use App\Domain\Reports\EvidenceKind;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Console\Command;

/**
 * It. 60 (US-029): the thumbnails of the photos published before they
 * existed, in every organization whose files are still there. Only what is
 * missing: running it again does nothing.
 */
class MakeEvidenceThumbnailsCommand extends Command
{
    protected $signature = 'evidence:thumbnails';

    protected $description = 'Make the thumbnails the published photos are missing';

    public function handle(): int
    {
        $made = 0;
        $failed = 0;

        foreach (Tenant::query()->whereNull('evidence_files_purged_at')->get() as $organization) {
            $organization->run(function () use (&$made, &$failed) {
                $thumbnails = new EvidenceThumbnails;
                Evidence::query()
                    ->where('kind', EvidenceKind::Photo->value)
                    ->whereNull('thumbnail_path')
                    ->whereHas('report', fn ($report) => $report->onPublicMap())
                    ->chunkById(200, function ($photos) use ($thumbnails, &$made, &$failed) {
                        foreach ($photos as $photo) {
                            $thumbnails->make($photo) ? $made++ : $failed++;
                        }
                    });
            });
        }

        $this->info("Miniaturas nuevas: {$made}.");
        if ($failed > 0) {
            $this->warn("No se pudieron generar {$failed}: esas fotos se muestran completas (ver el registro).");
        }

        return self::SUCCESS;
    }
}
