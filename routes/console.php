<?php

use App\Infrastructure\Scheduling\NightlySchedule;
use App\Jobs\ArchiveOldContracts;
use App\Jobs\CheckContractLifetime;
use App\Jobs\CheckOrganizationActivity;
use App\Jobs\CheckSealingQueue;
use App\Jobs\CheckSponsorBalance;
use App\Jobs\PurgeCitizenReports;
use App\Jobs\PurgeDecommissionedEvidence;
use App\Jobs\PurgeVeedorPseudonyms;
use App\Jobs\SendReviewDigests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// It. 45a: todas las horas de este archivo son de Colombia
// (config/app.php, schedule_timezone).

// US-013 y US-034: la sincronización SECOP de la madrugada y, una hora
// después, el cálculo de obras en riesgo. La hora sale del parámetro
// (US-038-CFG) cada vez que se arma el calendario: ver NightlySchedule.
NightlySchedule::register(app(Schedule::class));

// US-048-MNT: el primer día de cada mes, tras la sincronización y el cálculo
// de riesgo, los contratos cerrados hace más de 5 años salen al archivo.
app(Schedule::class)->job(new ArchiveOldContracts)
    ->monthlyOn(1, '05:00')
    ->name('contracts-archive')
    ->onOneServer();

// US-021: evidencias con más de 2 horas sin sellar, cada 15 minutos.
app(Schedule::class)->job(new CheckSealingQueue)
    ->everyFifteenMinutes()
    ->name('sealing-queue-check')
    ->onOneServer();

// US-022: el saldo de la cuenta patrocinadora, cada 15 minutos, y la
// vigencia del contrato de sellado, cada día a las 07:00. Sin contrato configurado (un entorno sin make contract-deploy)
// no hay nada que vigilar.
$sealingConfigured = fn () => filled(config('stellar.sealing_contract_id'));

app(Schedule::class)->job(new CheckSponsorBalance)
    ->everyFifteenMinutes()
    ->when($sealingConfigured)
    ->name('sponsor-balance-check')
    ->onOneServer();

app(Schedule::class)->job(new CheckContractLifetime)
    ->dailyAt('07:00')
    ->when($sealingConfigured)
    ->name('contract-lifetime-check')
    ->onOneServer();

// US-003b: la retención de los archivos de una organización dada de baja,
// cada día a la 01:00.
app(Schedule::class)->job(new PurgeDecommissionedEvidence)
    ->dailyAt('01:00')
    ->name('decommissioned-evidence-purge')
    ->onOneServer();

// US-054-RPT: organizaciones con 30 días sin actividad, cada día a las 08:00.
app(Schedule::class)->job(new CheckOrganizationActivity)
    ->dailyAt('08:00')
    ->name('organization-activity-check')
    ->onOneServer();

// R-MNT-03: la tabla seudónimo→veedor, 5 años desde su último reporte; cada
// día a la 01:30.
app(Schedule::class)->job(new PurgeVeedorPseudonyms)
    ->dailyAt('01:30')
    ->name('veedor-pseudonyms-purge')
    ->onOneServer();

// It. 45c (US-059-LEG): los informes de los ciudadanos, 30 días después de
// atendidos (se borra el correo) o descartados (se borran enteros); cada día a
// la 01:15.
app(Schedule::class)->job(new PurgeCitizenReports)
    ->dailyAt('01:15')
    ->name('citizen-reports-purge')
    ->onOneServer();

// US-060-MON (it. 43i, V9): a cada Administrador, cuántas evidencias esperan su
// revisión; una vez al día, a las 07:00, y solo si hay.
app(Schedule::class)->job(new SendReviewDigests)
    ->dailyAt('07:00')
    ->name('review-digest')
    ->onOneServer();
