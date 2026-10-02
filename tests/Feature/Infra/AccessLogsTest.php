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
        ->and($configuration)->toContain('$request_method $uri $server_protocol')
        ->and($configuration)->toMatch('/access_log\s+\S+\s+govtrace;/');

    preg_match('/log_format\s+govtrace\s+(.+?);/s', $configuration, $format);
    expect($format[1])->not->toMatch('/\$(request|request_uri|args|query_string|arg_\w+)\b/');
})->with(fn () => array_combine(array_map('basename', nginxConfigurations()), nginxConfigurations()));

it('logs only the path of each request in Apache', function () {
    $vhost = file_get_contents(base_path('docker/app/vhost.conf'));

    preg_match('/LogFormat\s+"(.+)"\s+govtrace/', $vhost, $format);

    expect($format[1] ?? '')->toContain('%m %U %H')
        ->and($format[1])->not->toMatch('/%r|%q/')
        ->and($vhost)->toContain('CustomLog /dev/stdout govtrace');
});
