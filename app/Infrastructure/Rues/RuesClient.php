<?php

namespace App\Infrastructure\Rues;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * It. 46b: los datos abiertos del RUES (Confecámaras, "Personas Naturales,
 * Personas Jurídicas y Entidades Sin Ánimo de Lucro", datos.gov.co c82u-588k,
 * un extracto mensual, sin credenciales). Trae lo inscrito en las cámaras de
 * comercio, también las veedurías; las de las personerías no están aquí.
 *
 * Cada consulta es corta y no reintenta: la revisión del Super Administrador
 * sigue sin el RUES si no responde (RuesLookup).
 */
class RuesClient
{
    private const ENDPOINT = 'https://www.datos.gov.co/resource/c82u-588k.json';

    private const METADATA = 'https://www.datos.gov.co/api/views/c82u-588k.json';

    private const FIELDS = 'razon_social,numero_identificacion,organizacion_juridica,estado_matricula,camara_comercio,matricula,fecha_matricula,fecha_actualizacion';

    /** @return list<array<string, string>> the rows with that NIT, without its check digit */
    public function byNit(string $nit): array
    {
        return $this->rows(sprintf("numero_identificacion='%s'", $this->digits($nit)));
    }

    /** @return list<array<string, string>> the rows with that registration number: it repeats from one chamber to another */
    public function byRegistration(string $registration): array
    {
        return $this->rows(sprintf("matricula='%s'", $this->digits($registration)));
    }

    /** The day the monthly extract was published, in Colombia: what the data are as of. */
    public function extractPublishedOn(): ?string
    {
        $published = Http::timeout(5)->connectTimeout(3)->get(self::METADATA)->throw()->json('rowsUpdatedAt');

        return is_int($published) ? Carbon::createFromTimestamp($published, 'America/Bogota')->toDateString() : null;
    }

    /** @return list<array<string, string>> */
    private function rows(string $where): array
    {
        return Http::timeout(10)->connectTimeout(5)
            ->get(self::ENDPOINT, ['$select' => self::FIELDS, '$where' => $where, '$limit' => 20])
            ->throw()
            ->json() ?? [];
    }

    /** Only digits reach the query: nothing the veeduría writes is passed to SoQL as is. */
    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value);
    }
}
