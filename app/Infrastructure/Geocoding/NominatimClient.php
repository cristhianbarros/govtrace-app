<?php

namespace App\Infrastructure\Geocoding;

use Illuminate\Support\Facades\Http;

/**
 * It. 68 (US-059-LEG, US-035): la búsqueda de OpenStreetMap (Nominatim), para
 * ubicar una obra por su dirección o un sitio conocido. Su política de uso
 * pide una aplicación que se identifique, una consulta por segundo como
 * máximo, nada de autocompletar y guardar lo que responde: PlaceSearch lo
 * cumple. Con NOMINATIM_URL, una instancia propia.
 *
 * https://operations.osmfoundation.org/policies/nominatim/
 */
class NominatimClient
{
    public const RESULTS = 5;

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $viewbox  west, north, east and south: only what falls inside
     * @return list<array<string, mixed>>
     */
    public function search(string $text, ?array $viewbox): array
    {
        return Http::timeout(5)->connectTimeout(3)
            ->withUserAgent((string) config('services.nominatim.user_agent'))
            ->get(rtrim((string) config('services.nominatim.url'), '/').'/search', array_filter([
                'q' => $text,
                'format' => 'jsonv2',
                'countrycodes' => 'co',
                'limit' => self::RESULTS,
                'accept-language' => 'es',
                'viewbox' => $viewbox !== null ? implode(',', $viewbox) : null,
                'bounded' => $viewbox !== null ? 1 : null,
            ], fn ($value) => $value !== null))
            ->throw()
            ->json() ?? [];
    }
}
