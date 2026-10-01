<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Audit\AuditLog;
use App\Domain\CitizenReports\CitizenReport;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\PurgeCitizenReports;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * Iteración 45c — la retención de los informes de los ciudadanos (D-V2-09,
 * por defecto en la 44f: se guardaban mientras la veeduría estuviera en
 * GovTrace). El correo solo sirve para responder (R-LEG-10): 30 días después
 * de que la veeduría atiende un informe, se borra el correo y queda el
 * informe; uno descartado se borra entero, con su foto. Uno sin atender se
 * guarda con su correo hasta que la veeduría lo atienda.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    reportableContract('CO1.PCCNTR.1234567');
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

/** A report of a citizen, in $status since $daysAgo days. */
function citizenReport(string $status, int $daysAgo, ?string $photo = null): int
{
    return test()->tenant->run(function () use ($status, $daysAgo, $photo) {
        if ($photo) {
            Storage::disk('evidencias')->put($photo, 'foto');
        }

        return CitizenReport::create([
            'worksite_id' => test()->worksite->id,
            'email' => 'vecina@correo.co',
            'email_hash' => hash('sha256', 'vecina@correo.co'),
            'message' => 'La valla está en el piso.',
            'photo_path' => $photo,
            'status' => $status,
            'answer' => $status === 'answered' ? 'Gracias, vamos a documentarlo.' : null,
            'handled_at' => $status === 'new' ? null : now()->subDays($daysAgo),
            'data_authorized_at' => now()->subDays($daysAgo + 1),
            'data_policy_version' => '2026-09-30.2',
            'created_at' => now()->subDays($daysAgo + 1),
        ])->id;
    });
}

function citizenReportRow(int $id): ?CitizenReport
{
    return test()->tenant->run(fn () => CitizenReport::query()->find($id)?->makeVisible(['email', 'email_hash']));
}

it('deletes a discarded report 30 days after it was discarded, with its photo', function () {
    $old = citizenReport('discarded', 31, 'citizen-reports/old.jpg');
    $recent = citizenReport('discarded', 29, 'citizen-reports/recent.jpg');

    (new PurgeCitizenReports)->handle();

    expect(citizenReportRow($old))->toBeNull()
        ->and(citizenReportRow($recent))->not->toBeNull();
    $this->tenant->run(fn () => expect(Storage::disk('evidencias')->exists('citizen-reports/old.jpg'))->toBeFalse()
        ->and(Storage::disk('evidencias')->exists('citizen-reports/recent.jpg'))->toBeTrue());
});

it('erases the email of an answered report 30 days after the answer, and keeps the report', function () {
    $old = citizenReport('answered', 31);
    $recent = citizenReport('answered', 29);

    (new PurgeCitizenReports)->handle();

    expect(citizenReportRow($old))->email->toBeNull()->email_hash->toBeNull()->message->toBe('La valla está en el piso.')->answer->toBe('Gracias, vamos a documentarlo.')
        ->and(citizenReportRow($recent)->email)->toBe('vecina@correo.co');
});

it('keeps a report not yet handled with its email, however old: the veeduría still has to answer it', function () {
    $waiting = citizenReport('new', 120);

    (new PurgeCitizenReports)->handle();

    expect(citizenReportRow($waiting)->email)->toBe('vecina@correo.co');
});

it('records in the audit log how many it deleted and how many emails it erased, never an email', function () {
    citizenReport('discarded', 40);
    citizenReport('answered', 40);
    citizenReport('answered', 35);

    (new PurgeCitizenReports)->handle();

    $entry = AuditLog::query()->where('action', 'citizen_reports.purged')->sole();
    expect($entry->organization_id)->toBe($this->tenant->id)
        ->and($entry->actor_type)->toBe('system')
        ->and($entry->after)->toBe(['reports_deleted' => 1, 'emails_erased' => 2])
        ->and(json_encode($entry->toArray()))->not->toContain('vecina@correo.co');
});

it('runs every day, at 01:15 in Colombia', function () {
    Artisan::call('schedule:list');
    $event = collect(app(Schedule::class)->events())->first(fn (Event $event) => $event->description === 'citizen-reports-purge');

    expect($event->expression)->toBe('15 1 * * *')
        ->and($event->timezone)->toBe('America/Bogota');
});
