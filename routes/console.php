<?php

use App\Infrastructure\Scheduling\NightlySchedule;
use App\Jobs\ArchiveOldContracts;
use App\Jobs\CheckContractLifetime;
use App\Jobs\CheckSealingQueue;
use App\Jobs\CheckSponsorBalance;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
// vigencia del contrato de sellado, cada día a las 12:00 UTC (07:00 en
// Colombia). Sin contrato configurado (un entorno sin make contract-deploy)
// no hay nada que vigilar.
$sealingConfigured = fn () => filled(config('stellar.sealing_contract_id'));

app(Schedule::class)->job(new CheckSponsorBalance)
    ->everyFifteenMinutes()
    ->when($sealingConfigured)
    ->name('sponsor-balance-check')
    ->onOneServer();

app(Schedule::class)->job(new CheckContractLifetime)
    ->dailyAt('12:00')
    ->when($sealingConfigured)
    ->name('contract-lifetime-check')
    ->onOneServer();
