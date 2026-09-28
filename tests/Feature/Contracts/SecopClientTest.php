<?php

use App\Infrastructure\Secop\SecopClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * Iteración 7 — cliente SODA de SECOP II. Verificación técnica, sin
 * escenario Gherkin propio: el filtro viaja a SECOP y se recorren todas
 * las páginas (un departamento como Antioquia supera las 1.000 filas).
 */

it('asks SECOP only for works contracts of the given department, in a stable order', function () {
    Http::fake(['www.datos.gov.co/*' => Http::response([])]);

    iterator_to_array((new SecopClient)->fetchWorksContracts('Magdalena'));

    Http::assertSent(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_starts_with($request->url(), 'https://www.datos.gov.co/resource/jbjy-vk9h.json')
            && $query['$where'] === "tipo_de_contrato='Obra' AND upper(departamento)=upper('Magdalena')"
            && $query['$order'] === ':id';
    });
});

it('follows every page until SECOP returns a short one', function () {
    Http::fake(['www.datos.gov.co/*' => Http::sequence()
        ->push([['id_contrato' => 'A'], ['id_contrato' => 'B']])
        ->push([['id_contrato' => 'C']]),
    ]);

    $rows = iterator_to_array((new SecopClient(pageSize: 2))->fetchWorksContracts('Magdalena'), false);

    expect(array_column($rows, 'id_contrato'))->toBe(['A', 'B', 'C']);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), urlencode('$offset').'=2'));
});
