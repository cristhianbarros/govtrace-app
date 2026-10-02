<?php

namespace App\Application\Organization;

use App\Domain\Organization\OrganizationRequest;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * It. 46b (US-062-ALT): el PDF de la resolución o del certificado de
 * inscripción que adjunta una veeduría al pedir su alta. Lo manda cualquiera,
 * sin cuenta: solo PDF (lo dicen sus primeros bytes, no su nombre), hasta 10
 * MB, guardado privado en el disco de las evidencias (que el respaldo copia).
 * Solo el Super Administrador lo descarga, como adjunto y en un contenedor
 * aislado (sandbox): un PDF puede traer código.
 *
 * Se borra con la solicitud si se rechaza; si se aprueba, pasa a la
 * organización, bajo su prefijo, como sus archivos de evidencia.
 */
final class RegistrationDocuments
{
    public const NEEDS_THE_PDF = 'Adjunte la resolución o el certificado de inscripción en PDF, de hasta 10 MB.';

    public const MAX_KILOBYTES = 10_240;

    private const DISK = 'evidencias';

    public static function assertAcceptable(mixed $file): UploadedFile
    {
        $isPdf = $file instanceof UploadedFile
            && $file->isValid()
            && $file->getSize() > 0
            && $file->getSize() <= self::MAX_KILOBYTES * 1024
            && str_starts_with((string) file_get_contents($file->getRealPath(), false, null, 0, 5), '%PDF-');

        if (! $isPdf) {
            throw ValidationException::withMessages(['document' => self::NEEDS_THE_PDF]);
        }

        return $file;
    }

    public static function keepForRequest(OrganizationRequest $request, UploadedFile $file): void
    {
        $path = "central/organization-requests/{$request->id}.pdf";
        Storage::disk(self::DISK)->putFileAs('central/organization-requests', $file, "{$request->id}.pdf");
        $request->forceFill(['document_path' => $path])->save();
    }

    /** Approved: the document stays with the organization. */
    public static function handOverTo(OrganizationRequest $request, Tenant $tenant): void
    {
        if ($request->document_path === null || ! Storage::disk(self::DISK)->exists($request->document_path)) {
            return;
        }

        $path = "{$tenant->id}/registro/inscripcion.pdf";
        Storage::disk(self::DISK)->move($request->document_path, $path);
        $tenant->update(['registration_document_path' => $path]);
        $request->forceFill(['document_path' => null])->save();
    }

    public static function forget(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public static function download(?string $path, string $name): StreamedResponse
    {
        abort_if($path === null || ! Storage::disk(self::DISK)->exists($path), 404);

        return Storage::disk(self::DISK)->download($path, Str::slug($name).'.pdf', [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
        ]);
    }
}
