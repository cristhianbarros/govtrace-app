<?php

use App\Domain\Organization\Exceptions\LogoRejected;
use App\Domain\Organization\SvgSanitizer;

/*
 * Iteración 21 — US-007, R-SEC-04: un logo SVG se guarda limpio. Lista de
 * elementos y atributos permitidos (lo que dibuja), no una lista de lo
 * prohibido: lo que no está en la lista se va. Además, el logo se sirve con
 * una CSP que no deja ejecutar nada (OrganizationProfileTest).
 */

function clean(string $svg): string
{
    return (new SvgSanitizer)->sanitize($svg);
}

it('keeps what draws: shapes, paths, gradients, text and their presentation attributes', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" width="128" height="128">'
        .'<defs><linearGradient id="g"><stop offset="0" stop-color="#0f172a"/></linearGradient></defs>'
        .'<g transform="translate(4 4)"><path d="M0 0L10 10" stroke="#fff" stroke-width="2" fill="url(#g)"/>'
        .'<circle cx="64" cy="64" r="40"/><text x="10" y="20" font-size="12">Ojo</text></g></svg>';

    $clean = clean($svg);

    foreach (['<linearGradient', 'stop-color="#0f172a"', 'd="M0 0L10 10"', 'fill="url(#g)"', '<circle', '>Ojo</text>', 'viewBox="0 0 128 128"', 'transform="translate(4 4)"'] as $kept) {
        expect($clean)->toContain($kept);
    }
});

it('removes scripts and every event handler', function () {
    $clean = clean('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script><rect width="10" height="10" onclick="alert(3)" onmouseover="alert(4)"/></svg>');

    expect($clean)->not->toContain('script')
        ->and($clean)->not->toMatch('/\son[a-z]+=/i')
        ->and($clean)->not->toContain('alert')
        ->and($clean)->toContain('<rect');
});

it('removes links and references that leave the document', function () {
    $clean = clean('<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
        .'<a href="javascript:alert(1)"><rect width="1" height="1"/></a>'
        .'<use xlink:href="https://evil.example/sprite.svg#x"/>'
        .'<use href="#local"/>'
        .'<image href="https://evil.example/pixel.png"/>'
        .'<rect id="local" width="1" height="1" fill="url(https://evil.example/track)"/></svg>');

    expect($clean)->not->toContain('javascript')
        ->and($clean)->not->toContain('evil.example')
        ->and($clean)->not->toContain('<image')
        ->and($clean)->toContain('href="#local"');
});

it('removes foreignObject, animations and style sheets, which can smuggle HTML or change links', function () {
    $clean = clean('<svg xmlns="http://www.w3.org/2000/svg">'
        .'<foreignObject><iframe xmlns="http://www.w3.org/1999/xhtml" src="https://evil.example"/></foreignObject>'
        .'<set attributeName="href" to="javascript:alert(1)"/><animate attributeName="href" values="javascript:alert(1)"/>'
        .'<style>@import url(https://evil.example/x.css);</style>'
        .'<rect width="1" height="1" style="fill:red;background:url(javascript:alert(1))"/></svg>');

    expect($clean)->not->toContain('foreignObject')
        ->and($clean)->not->toContain('iframe')
        ->and($clean)->not->toContain('<set')
        ->and($clean)->not->toContain('<animate')
        ->and($clean)->not->toContain('@import')
        ->and($clean)->not->toContain('javascript')
        ->and($clean)->toContain('fill:red');
});

it('rejects a document with a DOCTYPE or entities, instead of expanding them', function () {
    $billionLaughs = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY a "aaaaaaaaaa"><!ENTITY b "&a;&a;&a;&a;&a;">]><svg xmlns="http://www.w3.org/2000/svg"><text>&b;</text></svg>';
    $external = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><text>&x;</text></svg>';

    expect(fn () => clean($billionLaughs))->toThrow(LogoRejected::class)
        ->and(fn () => clean($external))->toThrow(LogoRejected::class);
});

it('rejects what is not an SVG', function (string $content) {
    expect(fn () => clean($content))->toThrow(LogoRejected::class, 'El formato del archivo no es válido. Solo se permiten imágenes PNG, JPG o SVG.');
})->with([
    'HTML' => ['<html><body><script>alert(1)</script></body></html>'],
    'XML roto' => ['<svg xmlns="http://www.w3.org/2000/svg"><rect'],
    'otra raíz' => ['<?xml version="1.0"?><note>hola</note>'],
]);
