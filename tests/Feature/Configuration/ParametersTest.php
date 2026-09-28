<?php

use App\Domain\Configuration\Parameters;
use App\Domain\Configuration\ParameterValue;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Iteración 6 — Datos legales, territorio, log de auditoría y parámetros
 * (specs/PLAN.md). Done-when extra: "test que prueba que la tabla de
 * parámetros devuelve el valor vigente en una fecha pasada" (base de
 * R-AUD-05). Sin CREATE DATABASE de por medio aquí, sí usa RefreshDatabase.
 */

uses(RefreshDatabase::class);

it('seeds the five known default parameters', function () {
    expect(Parameters::current('geofence_radius_meters'))->toBe('500')
        ->and(Parameters::current('closed_contract_report_window_months'))->toBe('12')
        ->and(Parameters::current('invitation_validity_hours'))->toBe('48')
        ->and(Parameters::current('relayer_balance_alert_threshold_pol'))->toBe('5')
        ->and(Parameters::current('secop_sync_hour'))->toBe('02:00');
});

it('returns the value that was in effect at a past moment, not the current one', function () {
    Parameters::set('test_param', 'old-value', now()->subDays(10));
    Parameters::set('test_param', 'new-value', now()->subDay());

    expect(Parameters::valueAt('test_param', now()->subDays(5)))->toBe('old-value')
        ->and(Parameters::current('test_param'))->toBe('new-value');
});

it('never updates a row in place — a change always inserts a new version', function () {
    $countBefore = ParameterValue::query()->where('key', 'geofence_radius_meters')->count();

    Parameters::set('geofence_radius_meters', '300');

    $countAfter = ParameterValue::query()->where('key', 'geofence_radius_meters')->count();

    expect($countAfter)->toBe($countBefore + 1);
});
