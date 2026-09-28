<?php

namespace App\Domain\Reports\Exceptions;

use DomainException;

/**
 * Why a report was not accepted (US-008). $field names the part of the
 * report the problem is about — the same key the endpoint answers with.
 * The messages in quotes in specs/criterios/US-008.yaml are verbatim.
 */
class ReportValidationException extends DomainException
{
    private function __construct(string $message, public readonly string $field)
    {
        parent::__construct($message);
    }

    public static function classificationRequired(): self
    {
        return new self('Seleccione la clasificación del reporte: Avance, Retraso o Abandono.', 'classification');
    }

    public static function invalidClassification(): self
    {
        return new self('La clasificación debe ser Avance, Retraso o Abandono.', 'classification');
    }

    public static function commentTooLong(int $maxLength): self
    {
        return new self("El comentario admite máximo {$maxLength} caracteres.", 'comment');
    }

    public static function locationRequired(): self
    {
        return new self('GovTrace requiere acceso a su ubicación exacta para certificar criptográficamente que la evidencia fue tomada en el sitio de la obra. Por favor habilite el GPS.', 'location');
    }

    public static function invalidLocation(): self
    {
        return new self('Las coordenadas recibidas no son válidas. Vuelva a capturar la ubicación.', 'location');
    }

    public static function gpsTooImprecise(?float $accuracyMeters, int $maxAccuracyMeters): self
    {
        $reading = $accuracyMeters === null
            ? 'No se recibió la precisión del GPS'
            : 'La precisión del GPS es de '.rtrim(rtrim(number_format($accuracyMeters, 2, '.', ''), '0'), '.').' m';

        return new self("{$reading} y se requieren {$maxAccuracyMeters} m o menos. Espere a tener mejor señal y vuelva a intentarlo.", 'accuracy_meters');
    }

    public static function outsideGeofence(float $distanceMeters, int $radiusMeters): self
    {
        $distanceKm = number_format($distanceMeters / 1000, 1, '.', '');

        return new self("Se encuentra a {$distanceKm} km de la ubicación oficial de la obra. Para prevenir fraudes, debe acercarse a un radio de {$radiusMeters} metros del proyecto.", 'location');
    }

    public static function evidenceRequired(): self
    {
        return new self('Adjunte de 1 a 5 fotos o un documento PDF.', 'files');
    }

    public static function unsupportedEvidenceType(): self
    {
        return new self('Solo se aceptan fotos en JPEG o un documento PDF; los videos y otros archivos no están permitidos.', 'files');
    }

    public static function evidenceTooLarge(): self
    {
        return new self('Cada archivo puede pesar máximo 10 MB.', 'files');
    }

    public static function invalidEvidenceCombination(): self
    {
        return new self('Un reporte lleva de 1 a 5 fotos o un único PDF; no se pueden mezclar.', 'files');
    }

    public static function tooManyPhotos(int $maxPhotos): self
    {
        return new self("Un reporte admite máximo {$maxPhotos} fotos.", 'files');
    }

    public static function evidenceHashMismatch(): self
    {
        return new self('Alerta de seguridad: El archivo fue alterado o corrompido durante la transmisión (el hash del servidor no coincide con el de su celular). Por favor, intente de nuevo.', 'files');
    }

    public static function contractNotFound(): self
    {
        return new self('No se encontró el contrato seleccionado.', 'secop_contract_id');
    }

    public static function contractOutsideTerritory(): self
    {
        return new self('Solo puede reportar obras del territorio de su organización.', 'secop_contract_id');
    }

    public static function contractNotReportable(): self
    {
        return new self('Este contrato ya no admite reportes: fue anulado, o terminó o se liquidó hace más tiempo del permitido.', 'secop_contract_id');
    }
}
