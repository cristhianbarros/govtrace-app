<?php

/*
 * Hotfix del 2026-10-21 — PHP 8.4.27 corrige dos fallas críticas que dejan
 * ejecutar código en el servidor sin cuenta (CVE-2026-109618 y
 * CVE-2026-109619). La imagen usa Apache con mod_php, la configuración de
 * mayor riesgo. La prueba corre dentro de la imagen, así que falla si vuelve
 * una imagen base anterior.
 */

it('runs on a PHP with the October 2026 remote code execution fixes', function () {
    expect(version_compare(PHP_VERSION, '8.4.27', '>='))
        ->toBeTrue('PHP '.PHP_VERSION.' tiene las fallas CVE-2026-109618 y CVE-2026-109619: hace falta 8.4.27 o posterior.');
});
