<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Publication\InclusionProof;
use App\Domain\Reports\Evidence;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * US-026: descargar un archivo publicado — el mismo binario que se selló —
 * y su prueba de inclusión, sin sesión. Solo de evidencias publicadas: una
 * retirada conserva su recibo, no sus archivos; una oculta o rechazada
 * nunca fue pública. En todos esos casos, 404, también por la dirección.
 *
 * Sin caché: un retiro (US-037) tiene que valer desde ese momento, también
 * para lo que un intermediario habría guardado.
 */
class PublicEvidenceController extends Controller
{
    private const NO_CACHE = ['Cache-Control' => 'no-store'];

    public function download(int $evidence): StreamedResponse
    {
        $file = $this->published($evidence);

        return Storage::disk('evidencias')->download($file->storage_path, $file->downloadName(), [
            'Content-Type' => $file->mime_type,
            ...self::NO_CACHE,
        ]);
    }

    public function proof(int $evidence): JsonResponse
    {
        $file = $this->published($evidence);

        return response()->json(InclusionProof::of($file), 200, [
            'Content-Disposition' => "attachment; filename={$file->proofName()}",
            ...self::NO_CACHE,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function published(int $evidence): Evidence
    {
        return Evidence::query()
            ->whereHas('report', fn ($report) => $report->onPublicMap())
            ->with('report.seal')
            ->findOrFail($evidence);
    }
}
