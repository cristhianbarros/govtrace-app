<?php

use App\Domain\Configuration\Parameters;
use App\Domain\Configuration\ParameterValue;
use App\Infrastructure\Scheduling\NightlySchedule;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

/*
 * Iteración 21 — US-038-CFG / US-013: la hora de la sincronización nocturna
 * sale del parámetro cada vez que schedule:run arma el calendario — y
 * schedule:work lo lanza como un proceso nuevo cada minuto —, así que un
 * cambio rige desde el minuto siguiente, sin reiniciar nada.
 */

beforeEach(function () {
    $this->artisan('migrate');
    $this->lastParameterVersion = (int) ParameterValue::query()->max('id');
});

afterEach(function () {
    ParameterValue::query()->where('id', '>', $this->lastParameterVersion)->delete();
});

it('still lists the nightly sync at the configured hour', function () {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toMatch('/0\s+2\s+\*\s+\*\s+\*\s+secop-sync-nightly/')
        ->and(Parameters::current('secop_sync_hour'))->toBe('02:00');
});

it('moves the risk calculation with the sync, one hour after it, so it uses the updated contracts', function () {
    Parameters::set('secop_sync_hour', '23:30');

    $schedule = new Schedule;
    NightlySchedule::register($schedule);
    $expressions = collect($schedule->events())->mapWithKeys(fn (Event $event) => [$event->description => $event->expression]);

    expect($expressions['secop-sync-nightly'])->toBe('30 23 * * *')
        ->and($expressions['calculate-worksites-at-risk'])->toBe('30 0 * * *');
});

it('falls back to 02:00 when the parameters cannot be read, as in the first migrate of a new database', function () {
    $schedule = new Schedule;
    NightlySchedule::register($schedule, fn () => throw new RuntimeException('no existe la tabla parameters'));

    expect(collect($schedule->events())->firstWhere('description', 'secop-sync-nightly')->expression)->toBe('0 2 * * *');
});
