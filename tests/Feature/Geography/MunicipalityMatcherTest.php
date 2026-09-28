<?php

use App\Domain\Geography\MunicipalityMatcher;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Iteración 2 — Datos de referencia: DIVIPOLA y emparejamiento (specs/PLAN.md).
 * Traduce el Esquema del escenario "Emparejamiento normalizado del municipio
 * con la tabla DIVIPOLA" de features/US-013.feature, como test unitario
 * del servicio (sin pasar por la sincronización SECOP, que llega en it. 7).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    (new DivipolaSeeder)->run();
});

it('matches the municipality name SECOP II sends, normalizing accents and case', function (string $municipioSecop, ?string $codigoEsperado) {
    $municipio = (new MunicipalityMatcher)->match($municipioSecop);

    if ($codigoEsperado === null) {
        expect($municipio)->toBeNull();
    } else {
        expect($municipio)->not->toBeNull()
            ->and($municipio->code)->toBe($codigoEsperado);
    }
})->with([
    'exact uppercase match' => ['SANTA MARTA', '47001'],
    'accented, matching the stored name exactly' => ['Ciénaga', '47189'],
    'unaccented, still resolves to the accented stored name' => ['Cienaga', '47189'],
    'unknown municipality is discarded, not guessed' => ['Villa Inexistente', null],
]);

// Iteración 7 — R-TST-02: variantes reales de SECOP II -------------------
// Pares departamento/ciudad tal como los escribe SECOP II, grabados de la
// API SODA (dataset jbjy-vk9h) el 2026-09-27. El municipio se busca
// DENTRO del departamento: 67 nombres DIVIPOLA se repiten entre
// departamentos (Armenia, Barbosa, San Andrés…).

it('does not guess a municipality name that exists in several departments', function () {
    expect((new MunicipalityMatcher)->match('Armenia'))->toBeNull()
        ->and((new MunicipalityMatcher)->match('Armenia', '63')?->code)->toBe('63001');
});

it('resolves the real SECOP II spellings recorded from the API', function (string $departamento, string $ciudad, ?string $divipola) {
    $municipio = (new MunicipalityMatcher)->matchSecopLocation($departamento, $ciudad);

    expect($municipio?->code)->toBe($divipola);
})->with(function () {
    $variants = secopFixture('location_variants');

    foreach ($variants as $v) {
        yield "{$v['departamento']} / {$v['ciudad']}" => [$v['departamento'], $v['ciudad'], $v['divipola']];
    }
});
