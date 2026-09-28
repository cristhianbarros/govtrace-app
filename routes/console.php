<?php

use App\Infrastructure\Scheduling\NightlySchedule;
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
