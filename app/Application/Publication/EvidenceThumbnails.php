<?php

namespace App\Application\Publication;

use App\Domain\Reports\Evidence;
use App\Domain\Reports\EvidenceKind;
use App\Domain\Reports\Report;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * It. 60 (US-029): the thumbnails of the published photos, for the card of a
 * worksite on the map and its timeline. A light copy to look at — 320 px on
 * its longer side, a few tens of KB instead of the hundreds of the photo —,
 * never what was sealed: the original stays as the phone sent it, and that
 * is what is verified. Made when the evidence is published, deleted when it
 * is withdrawn (US-037) or its files are purged (US-003b). A photo that can't
 * be shrunk is published anyway, and the full photo is shown.
 *
 * Inside the organization.
 */
final class EvidenceThumbnails
{
    public const MAX_SIDE = 320;

    private const QUALITY = 75;

    /** The thumbnails a report's photos don't have yet. */
    public function forReport(Report $report): void
    {
        $report->evidences()
            ->where('kind', EvidenceKind::Photo->value)
            ->whereNull('thumbnail_path')
            ->orderBy('id')
            ->get()
            ->each(fn (Evidence $photo) => $this->make($photo));
    }

    /** Whether the photo got its thumbnail; a failure only leaves it without one. */
    public function make(Evidence $photo): bool
    {
        try {
            // Al lado del original, nunca en su lugar: lo sellado no se toca (R-TA-02).
            $path = Str::beforeLast($photo->storage_path, '.').'.thumb.jpg';
            Storage::disk('evidencias')->put($path, $this->shrunk(Storage::disk('evidencias')->get($photo->storage_path)));
            $photo->update(['thumbnail_path' => $path]);

            return true;
        } catch (Throwable $failure) {
            Log::warning('No se pudo generar la miniatura de una foto publicada: se muestra la foto completa.', ['evidence_id' => $photo->id, 'error' => $failure->getMessage()]);

            return false;
        }
    }

    /** US-037: a withdrawn evidence keeps nothing to look at. */
    public function forget(Report $report): void
    {
        $report->evidences()->whereNotNull('thumbnail_path')->get()->each(function (Evidence $photo) {
            Storage::disk('evidencias')->delete($photo->thumbnail_path);
            $photo->update(['thumbnail_path' => null]);
        });
    }

    /** The image, 320 px at most on its longer side (never enlarged), as a JPEG without metadata. */
    private function shrunk(?string $bytes): string
    {
        $image = $bytes !== null ? @imagecreatefromstring($bytes) : false;
        if ($image === false) {
            throw new RuntimeException('No es una imagen que se pueda leer.');
        }

        $scale = min(1, self::MAX_SIDE / max(imagesx($image), imagesy($image)));
        $thumbnail = imagescale($image, max(1, (int) round(imagesx($image) * $scale)), max(1, (int) round(imagesy($image) * $scale)), IMG_BICUBIC);

        ob_start();
        imagejpeg($thumbnail, null, self::QUALITY);

        return (string) ob_get_clean();
    }
}
