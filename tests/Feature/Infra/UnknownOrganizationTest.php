<?php

use Illuminate\Support\Facades\Exceptions;

/*
 * Iteración 42b — hallazgo de staging: con DuckDNS, cualquier subdominio llega
 * a la máquina. Uno que no es de ninguna organización (un error al escribir la
 * dirección de una veeduría) respondía 500, "Error del servidor", y dejaba un
 * ERROR en el log: la excepción de Stancl al no encontrar la organización no
 * la manejaba nadie. Es "no encontrado".
 */

it('answers 404 on a subdomain that belongs to no organization', function () {
    $this->get('http://no-existe.govtrace.localhost/')->assertNotFound();
});

it('answers 404 in JSON to the app on a subdomain that belongs to no organization', function () {
    $this->getJson('http://no-existe.govtrace.localhost/public/worksites')->assertNotFound();
});

it('does not report an error for a subdomain that belongs to no organization', function () {
    Exceptions::fake();

    $this->get('http://no-existe.govtrace.localhost/')->assertNotFound();

    Exceptions::assertNothingReported();
});
