<?php

/*
 * Iteración 45f — los registros de acceso no guardan los parámetros de las
 * URL. Es la segunda barrera para la ubicación del veedor: "Obras cercanas"
 * ya la manda en el cuerpo (POST), pero si alguna petición llevara datos en
 * la URL, el proxy (nginx) y el servidor web (Apache) los anotarían con la
 * hora y la IP, también en producción.
 *
 * nginx: $request y $request_uri traen la URL con sus parámetros; $uri, solo
 * la ruta. Apache: %r es la línea de la petición completa y %q los
 * parámetros; %U, solo la ruta.
 */

/** @return list<string> the nginx configurations: development, and production and staging (the dataset is built before the app boots) */
function nginxConfigurations(): array
{
    $root = dirname(__DIR__, 3);

    return ["{$root}/docker/proxy/nginx.conf", "{$root}/docker/proxy/templates/prod.conf.template"];
}

it('logs only the path of each request in nginx, in development and in production', function (string $path) {
    $configuration = file_get_contents($path);

    expect($configuration)->toMatch('/log_format\s+govtrace\s/')
        ->and($configuration)->toContain('$request_method $govtrace_log_uri $server_protocol')
        ->and($configuration)->toMatch('/access_log\s+\S+\s+govtrace;/');

    preg_match('/log_format\s+govtrace\s+(.+?);/s', $configuration, $format);
    expect($format[1])->not->toMatch('/\$(request|request_uri|args|query_string|arg_\w+|http_referer|uri)\b/');

    // It. 45g: el token del enlace para restablecer la contraseña va en la ruta, y el de una invitación
    // puede volver en el Referer: los dos se enmascaran.
    expect($configuration)->toContain('~^/reset-password/.  "/reset-password/[token]";')
        ->and($configuration)->toContain('~/(set|reset)-password/  "[enlace con token]";')
        ->and($configuration)->toContain('"$govtrace_log_referer"');
})->with(fn () => array_combine(array_map('basename', nginxConfigurations()), nginxConfigurations()));

it('logs only the path of each request in Apache', function () {
    $vhost = file_get_contents(base_path('docker/app/vhost.conf'));

    preg_match('/LogFormat\s+"(.+)"\s+govtrace/', $vhost, $format);

    expect($format[1] ?? '')->toContain('%m %U %H')
        ->and($format[1])->not->toMatch('/%r|%q/')
        ->and($vhost)->toContain('CustomLog /dev/stdout govtrace');
});

it('masks the links with a token in Apache: the reset path, and a Referer from those pages (it. 45g)', function () {
    $vhost = file_get_contents(base_path('docker/app/vhost.conf'));

    preg_match('/LogFormat\s+"(.+)"\s+govtrace_private/', $vhost, $private);

    expect($vhost)->toContain('SetEnvIf Request_URI "^/reset-password/." govtrace_private')
        ->and($vhost)->toContain('SetEnvIf Referer "/(set|reset)-password/" govtrace_private')
        ->and($vhost)->toContain('CustomLog /dev/stdout govtrace env=!govtrace_private')
        ->and($vhost)->toContain('CustomLog /dev/stdout govtrace_private env=govtrace_private')
        ->and($private[1] ?? '')->toContain('[enlace con token]')
        ->and($private[1] ?? '')->not->toMatch('/%r|%q|%U|Referer/');
});

it('lets the application choose the Referrer-Policy, and keeps one for the files Apache serves alone (it. 45g)', function () {
    $vhost = file_get_contents(base_path('docker/app/vhost.conf'));

    // Sin "always": esa tabla no ve las cabeceras que pone PHP, y la respuesta saldría con dos.
    expect($vhost)->toContain('Header setifempty Referrer-Policy "strict-origin-when-cross-origin"')
        ->and($vhost)->not->toMatch('/Header\s+always\s+\w+\s+Referrer-Policy/');
});
