<?php

use App\Application\Organization\RegisterOrganization;
use App\Http\Middleware\SecurityHeaders;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;

/*
 * Iteración 41 — las cabeceras de seguridad de toda respuesta. La CSP nombra
 * los dos únicos orígenes externos que usa el navegador: las imágenes del mapa
 * (tile.openstreetmap.org) y el RPC público de Stellar del validador (US-024).
 * Todo lo demás, incluidos Leaflet y los estilos, sale del propio sitio.
 * Las fotos que el veedor elige se previsualizan como blob:.
 */

beforeEach(function () {
    $this->artisan('migrate');
    config(['stellar.public_rpc_url' => 'https://publico.rpc.example/token-restringido-al-dominio']);
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
});

/** @return array<string, string> the directives of the CSP of $response, by name */
function cspOf(TestResponse $response): array
{
    $directives = [];
    foreach (array_filter(array_map('trim', explode(';', (string) $response->headers->get('Content-Security-Policy')))) as $directive) {
        [$name, $value] = array_pad(explode(' ', $directive, 2), 2, '');
        $directives[$name] = $value;
    }

    return $directives;
}

it('sends a Content-Security-Policy with the map images and the public Stellar RPC as the only outside origins', function () {
    (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');

    foreach (['http://govtrace.localhost/login', 'http://veeduria-smr.govtrace.localhost/public/worksites', 'http://veeduria-smr.govtrace.localhost/verify'] as $url) {
        $csp = cspOf($this->withoutVite()->get($url));

        expect($csp)->toMatchArray([
            'default-src' => "'self'",
            // It. 46e: WebAssembly, y solo eso (no eval): TF.js lo intenta para enteros de 64 bits al cargar el detector de rostros.
            'script-src' => "'self' 'wasm-unsafe-eval'",
            'style-src' => "'self' 'unsafe-inline'",
            'img-src' => "'self' data: blob: https://tile.openstreetmap.org",
            // Solo el origen del RPC: la ruta, con el token del proveedor, no hace falta aquí.
            'connect-src' => "'self' https://publico.rpc.example",
            'font-src' => "'self' data:",
            'worker-src' => "'self'",
            'manifest-src' => "'self'",
            'object-src' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
            'frame-ancestors' => "'self'",
        ])->not->toHaveKey('upgrade-insecure-requests');
    }
});

it('leaves the public RPC out of the policy when there is none', function () {
    config(['stellar.public_rpc_url' => null]);

    expect(cspOf($this->withoutVite()->get('http://govtrace.localhost/login'))['connect-src'])->toBe("'self'");
});

// It. 45g: una página con un token en su URL (la invitación, el enlace para restablecer la contraseña)
// no lo repite en el Referer de lo que carga ni del formulario: el Referer queda en los registros de acceso.
it('sends no Referer from the pages with a token in their URL, and only the origin to other sites from the rest', function () {
    (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');

    foreach ([
        'http://govtrace.localhost/reset-password/token-de-prueba?email=ana%40correo.co',
        'http://govtrace.localhost/set-password/1?token=token-de-prueba',
        'http://veeduria-smr.govtrace.localhost/reset-password/token-de-prueba?email=ana%40correo.co',
        'http://veeduria-smr.govtrace.localhost/set-password/1?token=token-de-prueba',
    ] as $url) {
        expect($this->withoutVite()->get($url)->headers->get('Referrer-Policy'))->toBe('no-referrer');
    }

    expect($this->withoutVite()->get('http://govtrace.localhost/login')->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
});

it('allows the camera and the location only for the site itself', function () {
    expect($this->withoutVite()->get('http://govtrace.localhost/login')->headers->get('Permissions-Policy'))
        ->toBe('camera=(self), geolocation=(self), microphone=(), payment=(), usb=()');
});

it('adds HSTS and upgrade-insecure-requests only over HTTPS', function () {
    $plain = $this->withoutVite()->get('http://govtrace.localhost/login');
    $secure = $this->withoutVite()->withServerVariables(['REMOTE_ADDR' => '172.29.0.5'])
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('http://govtrace.localhost/login');

    expect($plain->headers->has('Strict-Transport-Security'))->toBeFalse()
        ->and(cspOf($plain))->not->toHaveKey('upgrade-insecure-requests')
        ->and($secure->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains')
        ->and(cspOf($secure))->toHaveKey('upgrade-insecure-requests');
});

it('keeps a stricter policy a response already brings, as the organization logo does', function () {
    $response = (new SecurityHeaders)->handle(
        Request::create('http://veeduria-smr.govtrace.localhost/organization/logo'),
        fn () => response('<svg/>')->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox"),
    );

    expect($response->headers->get('Content-Security-Policy'))->toBe("default-src 'none'; style-src 'unsafe-inline'; sandbox")
        ->and($response->headers->get('Permissions-Policy'))->not->toBeNull();
});

it('lets the Vite dev server in while it runs, in development only', function () {
    $hot = storage_path('framework/testing/vite.hot');
    @mkdir(dirname($hot), recursive: true);
    file_put_contents($hot, 'http://localhost:5173');
    Vite::useHotFile($hot);

    try {
        app()->detectEnvironment(fn () => 'local');
        $local = cspOf($this->get('http://govtrace.localhost/login'));

        app()->detectEnvironment(fn () => 'production');
        $production = cspOf($this->get('http://govtrace.localhost/login'));
    } finally {
        unlink($hot);
    }

    expect($local['script-src'])->toBe("'self' 'wasm-unsafe-eval' http://localhost:5173")
        ->and($local['style-src'])->toBe("'self' 'unsafe-inline' http://localhost:5173")
        ->and($local['connect-src'])->toContain('ws://localhost:5173')
        ->and($production['script-src'])->toBe("'self' 'wasm-unsafe-eval'");
});
