<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Organization\User;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/*
 * Iteración 41 — los límites de abuso. Cada reporte se sella y cuesta XLM de
 * la patrocinadora (~0,28 en testnet), y ocupa un turno de la selladora (it.
 * 39): una cuenta de veedor comprometida no puede mandar sin fin. Y las API
 * públicas no pueden servir a un solo visitante sin fin. Pasado el límite,
 * 429 con un mensaje en español; la PWA guarda el reporte en la bandeja de
 * salida y lo envía sola más tarde (NewReport.test.js).
 *
 * Detrás del proxy, "un visitante" es su IP real (X-Forwarded-For del proxy de
 * confianza), no la del proxy: si no, todos compartirían el mismo límite.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    reportableContract('CO1.PCCNTR.1234567');

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
});

/** A report of $veedor, sent to $host as a separate request. */
function reportFrom(User $veedor, string $host = 'veeduria-smr.govtrace.localhost'): TestResponse
{
    test()->flushSession();
    $response = sendReport($veedor, [], $host);
    tenancy()->end();

    return $response;
}

/** A GET to a public endpoint of the organization, from the visitor at $ip behind the proxy. */
function publicFrom(string $ip, string $path): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => '172.29.0.5'])
        ->withHeaders(['X-Forwarded-For' => $ip])
        ->getJson("http://veeduria-smr.govtrace.localhost{$path}");
}

it('stops a veedor who goes past the reports allowed per hour, says so in Spanish, and lets them send again an hour later', function () {
    config(['limits.reports_per_veedor_per_hour' => 3]);

    foreach (range(1, 3) as $report) {
        reportFrom($this->veedor)->assertCreated();
    }
    reportFrom($this->veedor)
        ->assertStatus(429)
        ->assertExactJson(['message' => 'Llegó al límite de 3 reportes por hora. Los siguientes se pueden enviar más tarde.']);

    $this->travel(61)->minutes();
    reportFrom($this->veedor)->assertCreated();
});

it('counts each veedor apart: another veedor, or one with the same id in another organization, can still send', function () {
    config(['limits.reports_per_veedor_per_hour' => 1]);
    $cienaga = (new RegisterOrganization)->handle('890000062-6', 'Veeduría Ciénaga', 'veeduria-cienaga');
    (new ConfigureTerritory)->handle($cienaga, ['47']);
    worksiteWithContracts($cienaga, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
    $theirVeedor = reportingMember($cienaga, 'ana@correo.co');
    expect($theirVeedor->id)->toBe($this->veedor->id);

    reportFrom($this->veedor)->assertCreated();
    reportFrom($this->veedor)->assertStatus(429);

    reportFrom(reportingMember($this->tenant, 'luis@correo.co'))->assertCreated();
    reportFrom($theirVeedor, 'veeduria-cienaga.govtrace.localhost')->assertCreated();
});

it('limits the public API per visitor, by the real IP behind the proxy', function () {
    config(['limits.public_requests_per_minute' => 2]);

    publicFrom('198.51.100.1', '/public/worksites')->assertOk();
    publicFrom('198.51.100.1', '/public/worksites/filters')->assertOk();
    publicFrom('198.51.100.1', '/public/stats')
        ->assertStatus(429)
        ->assertExactJson(['message' => 'Demasiadas consultas seguidas. Intente de nuevo en un minuto.']);

    publicFrom('198.51.100.2', '/public/worksites')->assertOk();
});

it('limits the open data downloads more tightly', function () {
    config(['limits.public_requests_per_minute' => 100, 'limits.open_data_per_minute' => 1]);

    publicFrom('198.51.100.1', '/open-data.json')->assertOk();
    publicFrom('198.51.100.1', '/open-data.csv')->assertStatus(429);
    publicFrom('198.51.100.1', '/public/worksites')->assertOk();
});

it('allows by default 30 reports per veedor per hour, 120 public requests per visitor per minute and 10 open data downloads', function () {
    expect(config('limits'))->toBe([
        'reports_per_veedor_per_hour' => 30,
        'public_requests_per_minute' => 120,
        'open_data_per_minute' => 10,
        // It. 44f: el canal del ciudadano, por conexión y por hora.
        'citizen_codes_per_hour' => 10,
        'citizen_reports_per_hour' => 20,
    ]);
});
