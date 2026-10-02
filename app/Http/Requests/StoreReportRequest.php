<?php

namespace App\Http\Requests;

use App\Application\Reports\NewReport;
use App\Domain\Reports\Blurring;
use App\Domain\Reports\EvidenceUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * A report as the PWA sends it (US-008) — also the one the Super
 * Administrador sends on an organization's behalf (US-042-SEC). Only the
 * shape of the request is checked here; every rule about the values is
 * the Domain's.
 */
class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quién puede, lo dice la ruta
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
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
            // It. 46e (R-PRIV-05): JSON, una entrada por archivo — lo difuminado en una foto, o null.
            'blurs' => ['nullable', 'string'],
        ];
    }

    public const BLURRING_INVALID = 'Los datos del difuminado de las fotos no son válidos.';

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->blurrings() === false) {
                $validator->errors()->add('blurs', self::BLURRING_INVALID);
            }
        }];
    }

    /** @return list<Blurring|null>|false|null one per file; false if it does not fit them; null if the app did not send it */
    private function blurrings(): array|false|null
    {
        if (! $this->filled('blurs')) {
            return null;
        }
        $entries = json_decode((string) $this->input('blurs'), true);
        if (! is_array($entries) || ! array_is_list($entries) || count($entries) !== count($this->file('files', []))) {
            return false;
        }
        $blurrings = array_map(fn (mixed $entry) => $entry === null ? null : Blurring::fromArray($entry), $entries);
        foreach ($entries as $position => $entry) {
            if ($entry !== null && $blurrings[$position] === null) {
                return false;
            }
        }

        return $blurrings;
    }

    public function toNewReport(): NewReport
    {
        $data = $this->validated();

        // Cada archivo con el SHA-256 que el teléfono calculó, en el mismo orden.
        $hashes = $data['hashes'] ?? [];
        $blurrings = $this->blurrings() ?: [];
        $files = array_values($this->file('files', []));
        $uploads = array_map(fn (UploadedFile $file, int $position) => new EvidenceUpload(
            path: (string) $file->getRealPath(),
            mimeType: (string) $file->getMimeType(),
            sizeBytes: (int) $file->getSize(),
            declaredSha256: $hashes[$position] ?? null,
            blurring: $blurrings[$position] ?? null,
        ), $files, array_keys($files));

        return new NewReport(
            secopContractId: $data['secop_contract_id'],
            classification: $data['classification'] ?? null,
            comment: $data['comment'] ?? null,
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            accuracyMeters: isset($data['accuracy_meters']) ? (float) $data['accuracy_meters'] : null,
            capturedAt: Carbon::parse($data['captured_at']),
            files: $uploads,
        );
    }
}
