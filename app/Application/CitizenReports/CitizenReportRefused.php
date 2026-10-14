<?php

namespace App\Application\CitizenReports;

use DomainException;

/** US-059-LEG: why a code or a report is refused — on a field (422), a limit (429) or a suspended veeduría (409). */
final class CitizenReportRefused extends DomainException
{
    public function __construct(string $message, public readonly int $status, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    public static function invalidCode(): self
    {
        return new self('El código no es válido o ya venció. Pida uno nuevo.', 422, 'code');
    }

    public static function authorizationRequired(): self
    {
        return new self('Para informar a la veeduría, autorice el tratamiento de sus datos personales.', 422, 'data_authorization');
    }

    public static function invalidPhoto(): self
    {
        return new self('Solo se acepta una foto en JPEG, de hasta 10 MB.', 422, 'photo');
    }

    public static function tooManyPhotos(int $max): self
    {
        return new self("Un informe admite hasta {$max} fotos.", 422, 'photo');
    }

    public static function photoWithMetadata(): self
    {
        return new self('La foto conserva metadatos, como la ubicación del teléfono. Elíjala desde esta página, que los quita.', 422, 'photo');
    }

    public static function tooManyCodes(int $max): self
    {
        return new self("Ya pidió {$max} códigos en la última hora. Espere un poco para pedir otro.", 429);
    }

    public static function dailyLimit(int $max): self
    {
        return new self("Llegó al límite de {$max} informes por día. Puede enviar más mañana.", 429);
    }

    public static function alreadyToday(): self
    {
        return new self('Ya informó hoy de esta obra. Puede volver a hacerlo mañana.', 429);
    }

    /** It. 58: a contract outside its territory, or one that can no longer be reported (R-SEC-07). */
    public static function notWatched(): self
    {
        return new self('Esa obra no está entre las que vigila esta veeduría.', 422, 'contract_id');
    }

    /** It. 58: a place proposed for the work, farther from its municipality than a first report may anchor it (R-GEO-01). */
    public static function farFromMunicipality(string $municipality, int $kilometers): self
    {
        return new self("Ese punto queda a más de {$kilometers} km de {$municipality}. Revise dónde marcó la obra, o envíe el informe sin la ubicación.", 422, 'location');
    }

    public static function invalidLocation(): self
    {
        return new self('La ubicación no es válida. Marque en el mapa dónde queda la obra.', 422, 'location');
    }

    public static function suspended(): self
    {
        return new self('Esta veeduría está suspendida y no recibe informes por ahora.', 409);
    }
}
