<?php

use App\Infrastructure\Scheduling\NightlySchedule;
use App\Jobs\CheckSealingQueue;
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

// US-021: evidencias con más de 2 horas sin sellar, cada 15 minutos.
app(Schedule::class)->job(new CheckSealingQueue)
    ->everyFifteenMinutes()
    ->name('sealing-queue-check')
    ->onOneServer();
