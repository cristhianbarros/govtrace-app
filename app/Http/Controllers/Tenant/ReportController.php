<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Reports\CreateReport;
use App\Application\Reports\NewReport;
use App\Domain\Reports\EvidenceUpload;
use App\Domain\Reports\Exceptions\ReportValidationException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * POST /reports (US-008) — the PWA's report endpoint. Only checks the
 * shape of the request; every rule about the values is the Domain's,
 * and a rejection comes back as a 422 under the field it is about.
 */
class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'secop_contract_id' => ['required', 'string'],
            'classification' => ['nullable', 'string'],
            'comment' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'accuracy_meters' => ['nullable', 'numeric'],
            'captured_at' => ['required', 'date'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file'],
            'hashes' => ['nullable', 'array'],
            'hashes.*' => ['nullable', 'string'],
        ]);

        // Cada archivo con el SHA-256 que el teléfono calculó, en el mismo orden.
        $hashes = $data['hashes'] ?? [];
        $files = array_values($request->file('files', []));
        $uploads = array_map(fn (UploadedFile $file, int $position) => new EvidenceUpload(
            path: (string) $file->getRealPath(),
            mimeType: (string) $file->getMimeType(),
            sizeBytes: (int) $file->getSize(),
            declaredSha256: $hashes[$position] ?? null,
        ), $files, array_keys($files));

        try {
            $report = (new CreateReport)->handle($request->user('tenant'), new NewReport(
                secopContractId: $data['secop_contract_id'],
                classification: $data['classification'] ?? null,
                comment: $data['comment'] ?? null,
                latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
                longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
                accuracyMeters: isset($data['accuracy_meters']) ? (float) $data['accuracy_meters'] : null,
                capturedAt: Carbon::parse($data['captured_at']),
                files: $uploads,
            ));
        } catch (ReportValidationException $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }

        return response()->json(['id' => $report->id], 201);
    }
}
