<?php

use App\Application\Dossier\DossierDocuments;
use App\Application\Dossier\WorksiteDossier;
use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Roles;
use App\Domain\Reports\Report;
use App\Domain\Sealing\MerkleTree;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Shared\PublicId;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 44b — El expediente de una obra (features/US-056-LEG.feature,
 * docs/proceso-actual.md A3). La vigilancia de una veeduría termina en un
 * derecho de petición o en una denuncia (Ley 850 de 2003, arts. 15 y 16), y
 * GovTrace terminaba en el mapa. El Administrador descarga, desde la obra,
 * un ZIP: el expediente y las dos plantillas en PDF, y cada archivo
 * publicado tal como se selló, con su prueba de inclusión.
 *
 * El contenido de los PDF se prueba en el HTML del que salen: el PDF mismo
 * solo se comprueba que lo es.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const DOSSIER_HOST = 'http://veeduria-smr.govtrace.localhost';

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    app()->instance(SealingNetwork::class, new FakeSealingNetwork);

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
    reportableContract('CO1.PCCNTR.1234567', ['contractor_name' => 'Constructora Bahía S.A.S.', 'value' => 2_850_000_000]);
    $this->worksite = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
});

/** The fixture photo with bytes of its own, so each one has its hash. */
function dossierPhoto(int $number): UploadedFile
{
    return UploadedFile::fake()->createWithContent("obra-{$number}.jpg", file_get_contents(base_path('tests/fixtures/evidence/foto.jpg'))."\x00expediente-{$number}");
}

/** Two published evidences of the worksite: a delay with two photos, then an abandonment with one. */
function twoPublishedEvidences(): array
{
    return [
        publishedReport(test()->tenant, test()->veedor, test()->administrator, ['files' => [dossierPhoto(1), dossierPhoto(2)], 'comment' => 'Obra detenida hace 2 meses']),
        publishedReport(test()->tenant, test()->veedor, test()->administrator, ['files' => [dossierPhoto(3)], 'classification' => 'Abandono', 'comment' => 'No hay personal ni cerramiento']),
    ];
}

function requestDossier(mixed $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->administrator, 'tenant')->get(DOSSIER_HOST.'/worksites/'.test()->worksite->public_id.'/dossier.zip');
}

/** @return array<string, string> the entries of the downloaded ZIP: name => bytes */
function downloadedDossier(): array
{
    $response = requestDossier()->assertOk();
    $zip = new ZipArchive;
    expect($zip->open($response->baseResponse->getFile()->getPathname()))->toBeTrue();

    $entries = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $entries[$zip->getNameIndex($index)] = $zip->getFromIndex($index);
    }
    $zip->close();
    tenancy()->end();

    return $entries;
}

/** What a document of the dossier says, as plain text: the HTML its PDF is rendered from. */
function dossierText(string $document): string
{
    $html = test()->tenant->run(fn () => (new DossierDocuments)->html($document, (new WorksiteDossier)->of(Worksite::query()->findOrFail(test()->worksite->id))));

    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html))));
}

/** @return list<object{sha256: string, name: string, proof_name: string}> the files of a report */
function filesOf(int $reportId): array
{
    return test()->tenant->run(fn () => Report::query()->findOrFail($reportId)->evidences()->orderBy('id')->get()
        ->map(fn ($evidence) => (object) ['id' => $evidence->id, 'public_id' => $evidence->public_id, 'sha256' => $evidence->sha256, 'name' => $evidence->downloadName(), 'proof_name' => $evidence->proofName()])->all());
}

it('Descargar el expediente de una obra: a ZIP with the dossier, the two templates, the readme and every published file with its proof', function () {
    [$delay, $abandonment] = twoPublishedEvidences();

    $response = requestDossier()->assertOk();
    expect($response->headers->get('Content-Disposition'))->toBe('attachment; filename=expediente-pavimentacion-calle-30-'.now()->timezone('America/Bogota')->toDateString().'.zip');
    tenancy()->end();

    $entries = downloadedDossier();
    $names = array_keys($entries);
    expect($names)->toContain('01-expediente.pdf', '02-derecho-de-peticion.pdf', '03-denuncia-contraloria.pdf', 'LEAME.txt')
        ->and(array_filter($names, fn (string $name) => str_starts_with($name, 'evidencias/')))->toHaveCount(6);
    foreach (['01-expediente.pdf', '02-derecho-de-peticion.pdf', '03-denuncia-contraloria.pdf'] as $pdf) {
        expect(substr($entries[$pdf], 0, 5))->toBe('%PDF-');
    }
    foreach ([...filesOf($delay), ...filesOf($abandonment)] as $file) {
        $inFolder = array_values(array_filter($names, fn (string $name) => str_ends_with($name, '/'.$file->name) || str_ends_with($name, '/'.$file->proof_name)));
        expect($inFolder)->toHaveCount(2);
    }
    expect($entries['LEAME.txt'])->toContain('Pavimentación Calle 30')->toContain('SHA-256');
});

it('Los archivos del expediente son los que se sellaron: byte for byte, with the proof of the public download', function () {
    [$delay] = twoPublishedEvidences();
    $entries = downloadedDossier();

    foreach (filesOf($delay) as $file) {
        $path = collect(array_keys($entries))->first(fn (string $name) => str_ends_with($name, '/'.$file->name));
        $proof = json_decode($entries[str_replace($file->name, $file->proof_name, $path)], true);

        expect(hash('sha256', $entries[$path]))->toBe($file->sha256)
            ->and($proof)->toBe(publicGet("/public/evidences/{$file->public_id}/proof")->assertOk()->json())
            ->and(MerkleTree::verify($file->sha256, $proof['proof'], $proof['merkle_root']))->toBeTrue();
    }
});

it('Cada archivo se cita como Prueba Pericial Criptográfica: its SHA-256, the Merkle root, the transaction and the ledger, and how to verify it without GovTrace', function () {
    [$delay] = twoPublishedEvidences();
    $seal = $this->tenant->run(fn () => ReportSeal::query()->where('report_id', $delay)->sole());
    $text = dossierText('expediente');

    expect($text)->toContain('Prueba Pericial Criptográfica')
        ->toContain('Ley 527 de 1999')
        ->toContain($seal->merkle_root)
        ->toContain($seal->tx_hash)
        ->toContain((string) $seal->ledger)
        ->toContain(FakeSealingNetwork::CONTRACT_ID)
        ->toContain(config('app.verifier_url'))
        ->toContain('sin depender de GovTrace');
    foreach (filesOf($delay) as $file) {
        expect($text)->toContain($file->sha256)->toContain($file->name);
    }
    // R-LEG-01: el estado de la obra, como alerta.
    expect($text)->toContain('Es una alerta de GovTrace')->toContain('Pavimentación Calle 30')->toContain('Constructora Bahía S.A.S.')->toContain('$ 2.850.000.000');
});

it('El derecho de petición llega pre-llenado: to the contracting entity, with the contract, the veeduría, the laws and blanks for whoever signs', function () {
    twoPublishedEvidences();
    $text = dossierText('peticion');

    expect($text)->toContain('Alcaldía Distrital de Santa Marta')
        ->toContain('Derecho de petición')
        ->toContain('CO1.PCCNTR.1234567')
        ->toContain('Pavimentación Calle 30')
        ->toContain('«Veeduría Ciudadana Santa Marta»')
        ->toContain('artículo 23 de la Constitución Política')
        ->toContain('Ley 1755 de 2015')
        ->toContain('Ley 850 de 2003')
        ->toContain('informes de supervisión o de interventoría')
        ->toContain('estado actual de la ejecución')
        ->toContain('diez (10) días')
        // Los hechos salen de las evidencias.
        ->toContain('Obra detenida hace 2 meses')
        ->toContain('No hay personal ni cerramiento')
        ->toContain('Prueba Pericial Criptográfica')
        // Quien lo presenta lo completa y lo firma.
        ->toContain('Yo, ______')
        ->toContain('C.C. n.º ______')
        ->toContain('Firma:');
});

it('La denuncia ante la Contraloría llega pre-llenada: the law, the facts, the proof, where to file it and which contraloría', function () {
    reportableContract('CO1.PCCNTR.7654321', ['object' => 'Interventoría de la Calle 30', 'end_date' => '2026-06-30']);
    $this->tenant->run(fn () => Worksite::query()->findOrFail($this->worksite->id)->contracts()->create(['secop_contract_id' => 'CO1.PCCNTR.7654321']));
    twoPublishedEvidences();
    $text = dossierText('denuncia');

    expect($text)->toContain('CONTRALORÍA GENERAL DE LA REPÚBLICA')
        ->toContain('artículo 69 de la Ley 1757 de 2015')
        ->toContain('artículo 16 de la Ley 850 de 2003')
        ->toContain('«Veeduría Ciudadana Santa Marta»')
        // Los hechos: lo que dice SECOP II y lo que vieron los veedores.
        ->toContain('terminaba el 30/06/2026, y SECOP II lo sigue mostrando «En ejecución»')
        ->toContain('No hay personal ni cerramiento')
        ->toContain('Prueba Pericial Criptográfica')
        ->toContain('artículo 70')
        // Dónde se radica, y cuál contraloría.
        ->toContain('Línea gratuita 199')
        ->toContain('https://denuncie.contraloria.gov.co:8443/sipar/')
        ->toContain('puede ser competente la contraloría de ese territorio')
        ->toContain('Yo, ______');
});

it('Solo van las evidencias publicadas: not the hidden, the rejected nor the withdrawn ones', function () {
    [$delay, $abandonment] = twoPublishedEvidences();
    $hidden = sealedReport($this->tenant, $this->veedor, ['files' => [dossierPhoto(4)], 'comment' => 'Sigue oculta']);
    $rejected = sealedReport($this->tenant, $this->veedor, ['files' => [dossierPhoto(5)], 'comment' => 'Fue rechazada']);
    editorialDecision($this->administrator, 'reject', $rejected, ['reason' => 'No corresponde a la obra'])->assertOk();
    $withdrawn = publishedReport($this->tenant, $this->veedor, $this->administrator, ['files' => [dossierPhoto(6)], 'comment' => 'Fue retirada']);
    editorialDecision($this->administrator, 'withdraw', $withdrawn, ['reason' => 'Aparece un menor de edad identificable'])->assertOk();
    tenancy()->end();

    $names = array_keys(downloadedDossier());
    expect(array_filter($names, fn (string $name) => str_starts_with($name, 'evidencias/')))->toHaveCount(6);
    foreach ([$hidden, $rejected, $withdrawn] as $excluded) {
        foreach (filesOf($excluded) as $file) {
            expect(implode("\n", $names))->not->toContain($file->name);
        }
    }

    foreach (['expediente', 'peticion', 'denuncia'] as $document) {
        expect(dossierText($document))->toContain('Obra detenida hace 2 meses')
            ->not->toContain('Sigue oculta')
            ->not->toContain('Fue rechazada')
            ->not->toContain('Fue retirada');
    }
});

it('Una obra sin evidencias publicadas también tiene expediente: it says so, and brings the petition to ask for information', function () {
    sealedReport($this->tenant, $this->veedor, ['files' => [dossierPhoto(1)]]);

    $entries = downloadedDossier();
    expect(array_keys($entries))->toBe(['01-expediente.pdf', '02-derecho-de-peticion.pdf', '03-denuncia-contraloria.pdf', 'LEAME.txt'])
        ->and(dossierText('expediente'))->toContain('Aún no hay evidencias publicadas de esta obra.')
        ->and(dossierText('peticion'))->toContain('informes de supervisión o de interventoría')->not->toContain('Prueba Pericial Criptográfica');
});

it('Cada descarga del expediente queda registrada: in the audit log, with the worksite, the number of files and who', function () {
    twoPublishedEvidences();

    requestDossier()->assertOk();
    requestDossier()->assertOk();
    tenancy()->end();

    $entries = AuditLog::query()->where('action', 'dossier.downloaded')->get();
    expect($entries)->toHaveCount(2)
        ->and($entries[0]->organization_id)->toBe($this->tenant->id)
        ->and($entries[0]->actor_type)->toBe('organization_admin')
        ->and($entries[0]->actor_id)->toBe((string) $this->administrator->id)
        ->and($entries[0]->after)->toBe(['worksite_id' => $this->worksite->id, 'worksite' => 'Pavimentación Calle 30', 'evidences' => 2, 'files' => 3]);
});

it('Solo el Administrador descarga el expediente: not a veedor, nor a visitor', function () {
    twoPublishedEvidences();

    requestDossier($this->veedor)->assertForbidden();
    publicGet('/worksites/'.$this->worksite->public_id.'/dossier.zip')->assertUnauthorized();
    tenancy()->end();

    expect(AuditLog::query()->where('action', 'dossier.downloaded')->exists())->toBeFalse();
});

it('answers 404 for a worksite that does not exist', function () {
    $this->actingAs($this->administrator, 'tenant')->get(DOSSIER_HOST.'/worksites/'.PublicId::generate().'/dossier.zip')->assertNotFound();
});

/*
 * Iteración 46j — el expediente, con lo que califica la Contraloría
 * (features/US-056-LEG.feature, docs/viabilidad-legal.md): el supervisor, el
 * origen de los recursos y a qué contraloría acudir, el lugar y las normas.
 */

it('El supervisor según SECOP II: the dossier gives its name, and says so when SECOP II does not', function () {
    reportableContract('CO1.PCCNTR.7654321', ['supervisor_name' => 'Pedro Gómez']);
    $this->tenant->run(fn () => Worksite::query()->findOrFail($this->worksite->id)->contracts()->create(['secop_contract_id' => 'CO1.PCCNTR.7654321']));

    expect(dossierText('expediente'))
        ->toContain('Supervisor (según SECOP II)Pedro Gómez')
        ->toContain('Supervisor (según SECOP II)Sin dato en SECOP II')
        ->and(dossierText('denuncia'))->toContain('Supervisor (según SECOP II)Pedro Gómez');
});

it('El origen de los recursos: the sources SECOP II gives, in pesos, and only those with money', function () {
    reportableContract('CO1.PCCNTR.7654321', ['entity_order' => 'Territorial', 'funding_sources' => ['pgn' => 0, 'sgp' => 150_000_000, 'sgr' => 0, 'territorial' => 60_000_000, 'credit' => 0, 'own' => 0]]);
    $this->tenant->run(fn () => Worksite::query()->findOrFail($this->worksite->id)->contracts()->create(['secop_contract_id' => 'CO1.PCCNTR.7654321']));

    expect(dossierText('expediente'))
        ->toContain('Sistema General de Participaciones: $ 150.000.000')
        ->toContain('Recursos propios del territorio: $ 60.000.000')
        ->not->toContain('Presupuesto General de la Nación: $')
        ->toContain('Orden de la entidadTerritorial')
        // Una contratación sin el desglose lo dice.
        ->toContain('Origen de los recursos Sin dato en SECOP II');
});

it('Con recursos de la Nación, la Contraloría General: the denuncia orients, and says the competence is the Contraloría\'s', function () {
    reportableContract('CO1.PCCNTR.7654321', ['funding_sources' => ['pgn' => 900_000_000, 'sgp' => 0, 'sgr' => 0, 'territorial' => 0, 'credit' => 0, 'own' => 0]]);
    $this->tenant->run(fn () => Worksite::query()->findOrFail($this->worksite->id)->contracts()->create(['secop_contract_id' => 'CO1.PCCNTR.7654321']));
    $text = dossierText('denuncia');

    expect($text)->toContain('A qué contraloría acudir')
        ->toContain('Con recursos de la Nación, la Contraloría General de la República')
        ->toContain('Es una orientación de GovTrace: la competencia la define la Contraloría')
        ->and(dossierText('peticion'))->not->toContain('A qué contraloría acudir');
});

it('Con recursos propios del territorio, su contraloría: the Contraloría General keeps the preferential control', function () {
    reportableContract('CO1.PCCNTR.7654321', ['funding_sources' => ['pgn' => 0, 'sgp' => 0, 'sgr' => 0, 'territorial' => 60_000_000, 'credit' => 0, 'own' => 0]]);
    $this->tenant->run(fn () => Worksite::query()->findOrFail($this->worksite->id)->contracts()->create(['secop_contract_id' => 'CO1.PCCNTR.7654321']));

    expect(dossierText('denuncia'))
        ->toContain('Con recursos propios del territorio, la contraloría de ese territorio')
        ->toContain('control prevalente de la Contraloría General');
});

it('Sin el origen de los recursos, la denuncia no lo inventa: it asks the entity and files with the Contraloría General', function () {
    expect(dossierText('denuncia'))
        ->toContain('SECOP II no informa el origen de los recursos')
        ->toContain('Ley 1755 de 2015, artículo 21');
});

it('El lugar de la obra, aproximado: the municipality, the approximate point with the map link, and a blank for the address', function () {
    $text = dossierText('expediente');

    expect($text)->toContain('Lugar de la obra')
        ->toContain('Santa Marta, Magdalena')
        ->toContain('http://veeduria-smr.govtrace.localhost/worksite/'.$this->worksite->public_id)
        ->toContain('Dirección o referencia: ____')
        // R-PRIV-02: nunca el punto exacto, que lo pudo fijar un veedor con su GPS.
        ->not->toContain((string) santaMartaWorksiteLocation()[0])
        ->and(dossierText('denuncia'))->toContain('Lugar de la obra');
});

it('Las normas incumplidas las escribe la veeduría: blank lines with an example, nothing suggested as a finding', function () {
    $text = dossierText('denuncia');

    expect($text)->toContain('Normas o cláusulas que se consideran incumplidas')
        ->toContain('Por ejemplo: la cláusula del plazo del contrato')
        ->toContain('Ley 1474 de 2011, artículos 83 y 84')
        ->toContain('________');
});
