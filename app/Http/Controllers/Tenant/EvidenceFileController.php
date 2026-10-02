<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Reports\Evidence;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /evidences/{id}/file (it. 18): the file exactly as it was sealed, so
 * the Administrador can look at it before publishing (US-036). Private:
 * the public download with its proof is US-026 (it. 23).
 */
class EvidenceFileController extends Controller
{
    public function show(string $evidence): StreamedResponse
    {
        $file = Evidence::byPublicId($evidence);

        return Storage::disk('evidencias')->response($file->storage_path, null, [
            'Content-Type' => $file->mime_type,
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
