<?php

use App\Providers\AppServiceProvider;

/*
 * Iteración 41 — Laravel detrás del proxy: nginx en desarrollo, y en staging
 * y producción el que termina el TLS. Se confía en el proxy de la red privada,
 * y solo en X-Forwarded-For y X-Forwarded-Proto, que nginx sobrescribe
 * siempre. En X-Forwarded-Host y -Port no: nginx los deja pasar tal como los
 * manda el visitante, y con ellos se envenenarían los enlaces que se arman con
 * el host de la petición, como la recuperación de contraseña del panel global.
 */

const PROXY_IP = '172.29.0.5';       // el contenedor del proxy, en la red de Docker
const OUTSIDER_IP = '203.0.113.7';   // cualquiera en internet

/** GET a page that needs a session, as it arrives from $remoteAddr with $headers: its redirect tells how Laravel sees the request. */
function guestRedirectFrom(string $remoteAddr, array $headers): string
{
    return test()->withServerVariables(['REMOTE_ADDR' => $remoteAddr])
        ->withHeaders($headers)
        ->get('http://govtrace.localhost/dashboard')
        ->assertRedirect()
        ->headers->get('Location');
}

it('knows a visit came over HTTPS when the proxy says so: https links and HSTS', function () {
    $response = $this->withServerVariables(['REMOTE_ADDR' => PROXY_IP])
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('http://govtrace.localhost/dashboard');

    expect($response->headers->get('Location'))->toBe('https://govtrace.localhost/login')
        ->and($response->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains');
});

it('ignores the proxy headers from anyone who is not the proxy', function () {
    $response = $this->withServerVariables(['REMOTE_ADDR' => OUTSIDER_IP])
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('http://govtrace.localhost/dashboard');

    expect($response->headers->get('Location'))->toBe('http://govtrace.localhost/login')
        ->and($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});

it('never takes the host or the port from forwarded headers, not even from the proxy', function () {
    $location = guestRedirectFrom(PROXY_IP, ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'atacante.example', 'X-Forwarded-Port' => '6666']);

    expect($location)->toBe('https://govtrace.localhost/login');
});

it('trusts the private networks where the proxy lives by default, and what TRUSTED_PROXIES says', function () {
    expect(config('app.trusted_proxies'))->toBe(['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);

    config(['app.trusted_proxies' => ['198.51.100.10']]);
    app()->getProvider(AppServiceProvider::class)->boot();

    expect(guestRedirectFrom('198.51.100.10', ['X-Forwarded-Proto' => 'https']))->toStartWith('https://')
        ->and(guestRedirectFrom(PROXY_IP, ['X-Forwarded-Proto' => 'https']))->toStartWith('http://');
});

it('asks nginx to drop the forwarded host and port the visitor may send', function () {
    $nginx = file_get_contents(base_path('docker/proxy/nginx.conf'));

    expect($nginx)->toContain('proxy_set_header X-Forwarded-Host  "";')
        ->toContain('proxy_set_header X-Forwarded-Port  "";');
});
