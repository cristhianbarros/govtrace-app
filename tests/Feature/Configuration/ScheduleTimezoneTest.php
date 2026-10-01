<?php

use App\Domain\Configuration\ConfigurableParameter;
use App\Domain\Configuration\ParameterValue;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

/*
 * Iteración 45a — el calendario corre en la hora de Colombia (deuda aceptada
 * hasta aquí: corría en UTC, y la sincronización "de las 02:00" era a las
 * 21:00 del día anterior). La hora del parámetro y la de cada tarea son las
 * de Colombia, sin cuentas de cabeza.
 */

beforeEach(function () {
    $this->artisan('migrate');
    $this->lastParameterVersion = (int) ParameterValue::query()->max('id');
});

afterEach(function () {
    ParameterValue::query()->where('id', '>', $this->lastParameterVersion)->delete();
});

it('runs every scheduled task in Colombia time, at the hours its comment says', function () {
    Artisan::call('schedule:list'); // arma el calendario de routes/console.php
    $events = collect(app(Schedule::class)->events())->keyBy(fn (Event $event) => $event->description);

    expect($events->map(fn (Event $event) => $event->timezone)->unique()->values()->all())->toBe(['America/Bogota'])
        ->and($events->map(fn (Event $event) => $event->expression)->only([
            'secop-sync-nightly', 'calculate-worksites-at-risk', 'contracts-archive', 'contract-lifetime-check',
            'decommissioned-evidence-purge', 'organization-activity-check', 'veedor-pseudonyms-purge',
        ])->all())->toBe([
            'secop-sync-nightly' => '0 2 * * *',
            'calculate-worksites-at-risk' => '0 3 * * *',
            'contracts-archive' => '0 5 1 * *',
            'contract-lifetime-check' => '0 7 * * *',
            'decommissioned-evidence-purge' => '0 1 * * *',
            'organization-activity-check' => '0 8 * * *',
            'veedor-pseudonyms-purge' => '30 1 * * *',
        ]);
});

it('says in the parameters panel that the sync hour is Colombia time', function () {
    expect(ConfigurableParameter::SecopSyncHour->label())->toBe('Hora de sincronización SECOP (hora de Colombia)');
});
