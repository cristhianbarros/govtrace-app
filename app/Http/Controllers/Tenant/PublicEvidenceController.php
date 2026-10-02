<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Publication\InclusionProof;
use App\Domain\Reports\Evidence;
use App\Domain\Reports\EvidenceKind;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * US-026: descargar un archivo publicado — el mismo binario que se selló —
 * y su prueba de inclusión, sin sesión; y ver una foto (US-029). Solo de evidencias publicadas: una
 * retirada conserva su recibo, no sus archivos; una oculta o rechazada
 * nunca fue pública. En todos esos casos, 404, también por la dirección.
 *
 * Sin caché: un retiro (US-037) tiene que valer desde ese momento, también
 * para lo que un intermediario habría guardado.
 */
class PublicEvidenceController extends Controller
{
    private const NO_CACHE = ['Cache-Control' => 'no-store'];

    /** US-003b: the file itself, until the retention after a decommission deletes it; its proof stays. */
    public const PURGED = 'Este archivo se borró al cumplirse 5 años de la baja de la organización. Su sello en Stellar y su prueba de inclusión se conservan.';

    public function download(string $evidence): StreamedResponse|JsonResponse
    {
        $file = $this->published($evidence);

        if (tenant()->evidenceFilesPurged()) {
            return response()->json(['message' => self::PURGED], 410);
        }

        return Storage::disk('evidencias')->download($file->storage_path, $file->downloadName(), [
            'Content-Type' => $file->mime_type,
            ...self::NO_CACHE,
        ]);
    }

    /** US-029: the photo itself, for the thumbnails and the viewer of the timeline — inline, the bytes that were sealed. */
    public function photo(string $evidence): StreamedResponse
    {
        $file = $this->published($evidence);
        abort_unless($file->kind === EvidenceKind::Photo->value, 404);

        return Storage::disk('evidencias')->response($file->storage_path, $file->downloadName(), [
            'Content-Type' => $file->mime_type,
            ...self::NO_CACHE,
        ]);
    }

    public function proof(string $evidence): JsonResponse
    {
        $file = $this->published($evidence);

        return response()->json(InclusionProof::of($file), 200, [
            'Content-Disposition' => "attachment; filename={$file->proofName()}",
            ...self::NO_CACHE,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function published(string $evidence): Evidence
    {
        return Evidence::query()
            ->whereHas('report', fn ($report) => $report->onPublicMap())
            ->with('report.seal')
            ->where('public_id', $evidence)
            ->firstOrFail();
    }
}
