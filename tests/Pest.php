<?php

use App\Domain\Contracts\Contract;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Reports\Report;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\ConfirmSeal;
use App\Jobs\SealReport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests extend Tests\TestCase (full Laravel app). Add
| Illuminate\Foundation\Testing\RefreshDatabase per test file when
| a scenario touches the database.
|
*/

pest()->extend(TestCase::class)
    ->beforeEach(function () {
        // QUEUE_CONNECTION=sync en testing (phpunit.xml) ejecuta cualquier
        // Job de inmediato, en el mismo test. Sin esto, cualquier test que
        // toque RegisterOrganization/ConfigureTerritory (it. 7 en adelante
        // los usa para disparar SyncSecopContracts) golpearía la API real
        // de SECOP. Un test que sí necesite correr el Job lo hace explícito
        // llamando dispatchSync() o instanciándolo directamente.
        Queue::fake();

        // R-TST-02: `make test` nunca llama a la API real de SECOP II (ni a
        // ningún otro servicio vía el cliente HTTP de Laravel). Un test que
        // olvide su Http::fake() falla en vez de salir a internet.
        Http::preventStrayRequests();
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * A recorded SODA response from tests/fixtures/secop (R-TST-02).
 *
 * @return list<array<string, mixed>>
 */
function secopFixture(string $name): array
{
    return json_decode(file_get_contents(__DIR__."/fixtures/secop/{$name}.json"), true);
}

/*
| Reportes (it. 10) — compartidos por CreateReportTest y FirstTouchRaceTest.
*/

/** The official location of the worksite in features/US-008.feature (Santa Marta). */
function santaMartaWorksiteLocation(): array
{
    return [11.2408, -74.1990];
}

function reportingMember(Tenant $tenant, string $email, Roles $role = Roles::Observer): OrganizationUser
{
    return $tenant->run(function () use ($email, $role) {
        $member = OrganizationUser::create(['name' => 'Miembro de prueba', 'email' => $email, 'password' => 'Veeduria#2026']);
        $member->assignRole($role->value);

        return $member;
    });
}

/**
 * A contract a veedor of a Magdalena-watching organization may report on.
 *
 * @param  array<string, mixed>  $overrides
 */
function reportableContract(string $secopContractId, array $overrides = []): Contract
{
    return Contract::fromSecop(fn () => Contract::create(array_merge([
        'secop_contract_id' => $secopContractId,
        'entity_name' => 'Alcaldía Distrital de Santa Marta',
        'object' => 'Pavimentación Calle 30',
        'contract_type' => 'Obra',
        'status' => 'En ejecución',
        'signed_at' => '2026-01-15',
        'end_date' => '2026-12-31',
        'department_code' => '47',
        'municipality_code' => '47001',
    ], $overrides)));
}

/**
 * @param  list<string>  $secopContractIds
 * @param  array{0: float, 1: float}|null  $location  null = ficha sin ubicación (Spatial-Null)
 */
function worksiteWithContracts(Tenant $tenant, array $secopContractIds, ?array $location): Worksite
{
    return $tenant->run(function () use ($secopContractIds, $location) {
        $worksite = Worksite::create([
            'latitude' => $location[0] ?? null,
            'longitude' => $location[1] ?? null,
            'located_at' => $location ? now() : null,
        ]);

        foreach ($secopContractIds as $secopContractId) {
            $worksite->contracts()->create(['secop_contract_id' => $secopContractId]);
        }

        return $worksite;
    });
}

/**
 * A point $meters to the north of $origin, on the same sphere the
 * geofence uses (R = 6.371 km) — so a distance there comes out exact.
 *
 * @param  array{0: float, 1: float}  $origin
 * @return array{0: float, 1: float}
 */
function pointMetersNorthOf(array $origin, float $meters): array
{
    return [$origin[0] + rad2deg($meters / 6_371_000), $origin[1]];
}

/** A real JPEG, as the PWA sends it: already optimized, no EXIF (R-PRIV-06). */
function evidencePhoto(string $name = 'foto.jpg'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, file_get_contents(__DIR__.'/fixtures/evidence/foto.jpg'));
}

/** A real one-page PDF, metadata already cleaned by the PWA (R-PRIV-04). */
function evidencePdf(string $name = 'acta.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, file_get_contents(__DIR__.'/fixtures/evidence/acta.pdf'));
}

/** The SHA-256 the phone computes before sending (R-HASH-01). */
function sha256Of(UploadedFile $file): string
{
    return hash_file('sha256', $file->getRealPath());
}

/**
 * POST /reports as $veedor. By default a valid report: 120 m from the
 * worksite of CO1.PCCNTR.1234567, 15 m of GPS accuracy, captured 2 min
 * ago, with 1 photo and its SHA-256 (the hashes follow the files unless
 * the test overrides them).
 *
 * @param  array<string, mixed>  $overrides
 */
function sendReport(OrganizationUser $veedor, array $overrides = [], string $host = 'veeduria-smr.govtrace.localhost'): TestResponse
{
    [$latitude, $longitude] = pointMetersNorthOf(santaMartaWorksiteLocation(), 120);
    $files = $overrides['files'] ?? [evidencePhoto()];

    return test()->actingAs($veedor, 'tenant')->postJson("http://{$host}/reports", array_merge([
        'secop_contract_id' => 'CO1.PCCNTR.1234567',
        'classification' => 'Retraso',
        'comment' => 'Obra detenida hace 2 meses',
        'latitude' => $latitude,
        'longitude' => $longitude,
        'accuracy_meters' => 15,
        'captured_at' => now()->subMinutes(2)->toIso8601String(),
        'files' => $files,
        'hashes' => array_map(sha256Of(...), $files),
    ], $overrides));
}

/**
 * A report by $veedor that went all the way to "Sellada" on the sealing
 * network bound in the container (a FakeSealingNetwork in the fast suite).
 * Every sealed evidence is born "Oculto" (US-036).
 *
 * @param  array<string, mixed>  $overrides  for sendReport
 */
function sealedReport(Tenant $tenant, OrganizationUser $veedor, array $overrides = []): int
{
    // Cada organización es otro subdominio y otra sesión, como en un navegador.
    test()->flushSession();

    $reportId = sendReport($veedor, $overrides, $tenant->domains()->value('domain'))->assertCreated()->json('id');

    app()->call([new SealReport($tenant->id, $reportId), 'handle']);
    app()->call([new ConfirmSeal($tenant->id, $reportId), 'handle']);

    // La petición dejó activo el contexto de la organización; el test sigue en el central.
    tenancy()->end();

    return $reportId;
}

/** GET /inbox: the Administrador's review inbox (US-036). */
function inbox(OrganizationUser $member, string $host = 'veeduria-smr.govtrace.localhost'): TestResponse
{
    return test()->actingAs($member, 'tenant')->getJson("http://{$host}/inbox");
}

/**
 * POST /reports/{id}/publish, /reject or /withdraw (US-036, US-037).
 *
 * @param  array<string, mixed>  $data
 */
function editorialDecision(OrganizationUser $member, string $decision, int $reportId, array $data = [], string $host = 'veeduria-smr.govtrace.localhost'): TestResponse
{
    return test()->actingAs($member, 'tenant')->postJson("http://{$host}/reports/{$reportId}/{$decision}", $data);
}

/** "Oculto", "Publicado", "Rechazado" or "Retirado". */
function editorialStatusOf(Tenant $tenant, int $reportId): string
{
    return $tenant->run(fn () => Report::query()->findOrFail($reportId)->editorial_status->label());
}
