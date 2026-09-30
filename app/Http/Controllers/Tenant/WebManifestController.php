<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * It. 43b (V6 de docs/mapa-funcional.md): el manifiesto de la app del veedor,
 * con el nombre de su veeduría, para instalarla en el celular: un ícono en la
 * pantalla de inicio que abre directo en "Nuevo Reporte", sin tener que
 * recordar la dirección. El Service Worker (public/sw.js) ya la abre sin señal.
 */
class WebManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $name = tenant()->freshDisplay()['name'];

        return response()->json([
            'name' => "GovTrace · {$name}",
            'short_name' => mb_strimwidth($name, 0, 12, '…'),
            'description' => "La app de los veedores de {$name}: reportar el avance de las obras públicas con fotos selladas.",
            'lang' => 'es-CO',
            'start_url' => '/reports/new',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#f8fafc',
            'theme_color' => '#0f172a',
            'icons' => [
                ['src' => '/pwa/govtrace-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/pwa/govtrace-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => '/pwa/govtrace.svg', 'sizes' => 'any', 'type' => 'image/svg+xml'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }
}
