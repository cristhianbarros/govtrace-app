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
