<?php

use App\Domain\Configuration\Parameters;
use App\Jobs\SyncSecopContracts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// US-013: corrida nocturna sobre todos los territorios de organizaciones
// activas. Lee la hora configurada UNA VEZ, al registrar el schedule —
// un cambio posterior en el panel de parámetros (US-038-CFG, it. 21) no
// se refleja aquí sin reiniciar el proceso que corre `schedule:run`.
//
// try/catch a propósito: routes/console.php se carga en TODO comando de
// artisan, incluido el primer `migrate` de una base recién creada, antes
// de que exista la tabla `parameters`.
try {
    $secopSyncHour = Parameters::current('secop_sync_hour') ?? '02:00';
} catch (Throwable) {
    $secopSyncHour = '02:00';
}

Schedule::job(new SyncSecopContracts)
    ->dailyAt($secopSyncHour)
    ->name('secop-sync-nightly')
    ->onOneServer();
