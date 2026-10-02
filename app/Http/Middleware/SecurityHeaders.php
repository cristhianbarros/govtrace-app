<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * It. 41: las cabeceras de seguridad de toda respuesta.
 *
 * La CSP nombra los dos únicos orígenes externos que usa el navegador: las
 * imágenes del mapa (Leaflet, desde OpenStreetMap) y el RPC público de
 * Stellar, que el validador consulta por su cuenta (US-024, D13). Todo lo
 * demás sale del propio sitio. Las fotos que elige el veedor se previsualizan
 * como blob:, y Leaflet pone estilos en línea.
 *
 * Por HTTPS, además, HSTS (un año, con los subdominios: cada organización es
 * uno) y upgrade-insecure-requests. Una respuesta que ya trae su CSP (el logo,
 * más estricta) la conserva.
 *
 * It. 45g: el Referer. Una página con un token en su URL (la invitación, el
 * enlace para restablecer la contraseña) no lo repite en lo que carga ni en su
 * formulario: el Referer queda en los registros de acceso. Las demás, solo el
 * origen hacia otros sitios.
 */
class SecurityHeaders
{
    private const MAP_TILES = 'https://tile.openstreetmap.org';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->policy($request));
        }
        $response->headers->set('Permissions-Policy', 'camera=(self), geolocation=(self), microphone=(), payment=(), usb=()');
        $response->headers->set('Referrer-Policy', $request->is('set-password/*', 'reset-password/*') ? 'no-referrer' : 'strict-origin-when-cross-origin');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function policy(Request $request): string
    {
        $dev = $this->viteDevServer();

        $directives = [
            'default-src' => ["'self'"],
            // It. 46e (R-PRIV-05): el detector de rostros (TF.js) compila WebAssembly al cargar
            // (long.js, enteros de 64 bits). 'wasm-unsafe-eval' permite solo eso, no eval de JavaScript.
            'script-src' => ["'self'", "'wasm-unsafe-eval'", $dev],
            'style-src' => ["'self'", "'unsafe-inline'", $dev],
            'img-src' => ["'self'", 'data:', 'blob:', self::MAP_TILES],
            'connect-src' => ["'self'", self::origin(config('stellar.public_rpc_url')), $dev, $dev === null ? null : preg_replace('#^http#', 'ws', $dev)],
            'font-src' => ["'self'", 'data:'],
            'worker-src' => ["'self'"],
            'manifest-src' => ["'self'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
        ];
        if ($request->isSecure()) {
            $directives['upgrade-insecure-requests'] = [];
        }

        $policy = [];
        foreach ($directives as $name => $sources) {
            $policy[] = trim($name.' '.implode(' ', array_filter($sources)));
        }

        return implode('; ', $policy);
    }

    /** While `npm run dev` runs, in development only: its origin (the browser loads the scripts from there). */
    private function viteDevServer(): ?string
    {
        if (! app()->environment('local') || ! Vite::isRunningHot()) {
            return null;
        }

        return self::origin(trim((string) file_get_contents(Vite::hotFile())));
    }

    /** scheme://host[:port] of a URL: a CSP needs no path, and this one would carry the provider's token. */
    private static function origin(?string $url): ?string
    {
        $parts = $url ? parse_url($url) : false;
        if (! $parts || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
