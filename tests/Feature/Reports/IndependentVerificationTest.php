<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Publication\PublicTimeline;
use App\Domain\Organization\Roles;
use App\Domain\Reports\Report;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealStatus;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\ConfirmSeal;
use App\Jobs\SealReport;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
 * Iteración 23 — US-026 de punta a punta, contra la red local de verdad: un
 * reporte sellado por StellarSealingNetwork, publicado, y su archivo y su
 * prueba descargados por los endpoints públicos, tal como los baja
 * cualquiera. Los deja en storage/framework/testing/verify para que
 * make verify-check los compruebe con tools/verify — sin GovTrace, solo con
 * el nodo de Stellar.
 */

pest()->group('stellar');

const VERIFY_HOST = 'http://veeduria-smr.govtrace.localhost';

it('publishes a file and a proof that the independent verifier checks against the network alone', function () {
    // Antes de cualquier organización: con una activa, storage_path() es el de ella.
    $directory = storage_path('framework/testing/verify');
    Http::allowStrayRequests();
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();

    $tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($tenant, ['47']);
    $administrator = reportingMember($tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $veedor = reportingMember($tenant, 'carlos@correo.co');
    reportableContract('CO1.PCCNTR.1234567');
    $worksite = worksiteWithContracts($tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());

    try {
        $reportId = createdReportId(sendReport($veedor));
        tenancy()->end();

        app()->call([new SealReport($tenant->id, $reportId), 'handle']);
        for ($attempt = 0; $attempt < 30; $attempt++) {
            app()->call([new ConfirmSeal($tenant->id, $reportId), 'handle']);
            if ($tenant->run(fn () => ReportSeal::query()->where('report_id', $reportId)->sole()->status) === SealStatus::Sealed) {
                break;
            }
            usleep(500_000);
        }

        editorialDecision($administrator, 'publish', $reportId)->assertOk();
        auth('tenant')->logout();
        $seal = $tenant->run(fn () => ReportSeal::query()->where('report_id', $reportId)->sole());
        $file = collect($tenant->run(fn () => (new PublicTimeline)->handle($worksite->id)))->firstWhere('report_id', $tenant->run(fn () => Report::query()->findOrFail($reportId)->public_id))['files'][0]; // it. 46c

        $download = $this->get(VERIFY_HOST.$file['download_url'])->assertOk();
        $proof = $this->get(VERIFY_HOST.$file['proof_url'])->assertOk();

        expect($proof->json('stellar'))->toMatchArray([
            'contract_id' => config('stellar.sealing_contract_id'),
            'tx_hash' => $seal->tx_hash,
            'ledger' => $seal->ledger,
        ]);

        File::ensureDirectoryExists($directory);
        File::put("{$directory}/".$proof->json('file.name'), $download->streamedContent());
        File::put("{$directory}/".str_replace('.jpg', '.prueba.json', $proof->json('file.name')), $proof->getContent());
    } finally {
        if (tenant()) {
            tenancy()->end();
        }
        Tenant::query()->get()->each->delete();
        DB::table('contracts')->delete();
        DB::table('audit_logs')->delete();
    }
});
