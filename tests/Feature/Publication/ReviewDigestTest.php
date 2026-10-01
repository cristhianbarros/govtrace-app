<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Organization\SuspendOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Organization\Notifications\ReviewDigest;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\SendReviewDigests;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 43i — V9 de docs/mapa-funcional.md (features/US-060-MON.feature):
 * una vez al día, cada Administrador recibe un correo con cuántas evidencias
 * esperan su revisión, en lugar de una alerta por cada una (decisión del
 * usuario, 2026-10-01: evitar la fatiga de alertas).
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    app()->instance(SealingNetwork::class, new FakeSealingNetwork);
    Notification::fake();

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
    reportableContract('CO1.PCCNTR.1234567');
    worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }
    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
});

/** The digest $member got, rendered as the mail it reads. */
function digestMailOf(OrganizationUser $member): MailMessage
{
    return Notification::sent($member, ReviewDigest::class)->sole()->toMail($member);
}

it('El resumen diario llega con las evidencias por revisar: how many, and a button to the inbox', function () {
    foreach (range(1, 3) as $report) {
        sealedReport($this->tenant, $this->veedor);
    }

    (new SendReviewDigests)->handle();

    $mail = digestMailOf($this->administrator);
    expect($mail->subject)->toBe('GovTrace: 3 evidencias por revisar en Veeduría Ciudadana Santa Marta')
        ->and($mail->introLines)->toContain('3 evidencias de sus veedores esperan su revisión. Mientras no las revise, no aparecen en el mapa público.')
        ->and($mail->actionText)->toBe('Revisar la Bandeja')
        ->and($mail->actionUrl)->toContain('veeduria-smr.govtrace.localhost')->toEndWith('/admin/inbox')
        ->and($mail->outroLines)->toContain('Le llega este resumen una vez al día, solo cuando hay evidencias por revisar.');
});

it('Sin evidencias por revisar no hay resumen', function () {
    (new SendReviewDigests)->handle();

    Notification::assertNotSentTo($this->administrator, ReviewDigest::class);
});

it('Solo cuentan las evidencias que ya se pueden publicar: not one still being sealed, nor one published', function () {
    sealedReport($this->tenant, $this->veedor);
    sendReport($this->veedor)->assertCreated(); // en cola de sellado: todavía no se puede publicar
    tenancy()->end();
    publishedReport($this->tenant, $this->veedor, $this->administrator);

    (new SendReviewDigests)->handle();

    $mail = digestMailOf($this->administrator);
    expect($mail->subject)->toBe('GovTrace: 1 evidencia por revisar en Veeduría Ciudadana Santa Marta')
        ->and($mail->introLines)->toContain('1 evidencia de sus veedores espera su revisión. Mientras no la revise, no aparece en el mapa público.');
});

it('El resumen no llega a quien no puede revisar: a deactivated Administrador, one who did not activate the account, or a veedor', function () {
    sealedReport($this->tenant, $this->veedor);
    sealedReport($this->tenant, $this->veedor);
    $deactivated = reportingMember($this->tenant, 'antes@veeduria-smr.org', Roles::Administrator);
    $this->tenant->run(fn () => OrganizationUser::query()->whereKey($deactivated->id)->update(['is_active' => false]));
    $pending = reportingMember($this->tenant, 'nueva@veeduria-smr.org', Roles::Administrator);
    $this->tenant->run(fn () => OrganizationUser::query()->whereKey($pending->id)->update(['invitation_token_hash' => hash('sha256', 'x'), 'invitation_expires_at' => now()->addDay()]));

    (new SendReviewDigests)->handle();

    Notification::assertSentTo($this->administrator, ReviewDigest::class);
    foreach ([$deactivated, $pending, $this->veedor] as $member) {
        Notification::assertNotSentTo($member, ReviewDigest::class);
    }
});

it('Una organización suspendida no recibe resumen', function () {
    sealedReport($this->tenant, $this->veedor);
    sealedReport($this->tenant, $this->veedor);
    (new SuspendOrganization)->handle($this->tenant);

    (new SendReviewDigests)->handle();

    Notification::assertNotSentTo($this->administrator, ReviewDigest::class);
});

it('can send the digest of one organization only', function () {
    sealedReport($this->tenant, $this->veedor);
    $other = (new RegisterOrganization)->handle('890000062-6', 'Veeduría Ciénaga', 'veeduria-cienaga');

    (new SendReviewDigests($other->id))->handle();

    Notification::assertNotSentTo($this->administrator, ReviewDigest::class);
});

it('runs every day at 07:00 in Colombia', function () {
    Artisan::call('schedule:list');
    $event = collect(app(Schedule::class)->events())->first(fn (Event $event) => $event->description === 'review-digest');

    expect($event->expression)->toBe('0 7 * * *')
        ->and($event->timezone)->toBe('America/Bogota');
});

it('speaks as "usted", without technical words (R-UX-06)', function () {
    sealedReport($this->tenant, $this->veedor);
    (new SendReviewDigests)->handle();

    $text = strip_tags((string) digestMailOf($this->administrator)->render());
    expect($text)->not->toMatch('/\b(tu|tus|te|revisa|blockchain|hash|stellar|ledger)\b/iu');
});
