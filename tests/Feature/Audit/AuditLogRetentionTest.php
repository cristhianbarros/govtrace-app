<?php

use App\Domain\Audit\AuditLog;
use App\Domain\Audit\Exceptions\AuditLogIsAppendOnly;
use Illuminate\Support\Facades\DB;

/*
 * Iteración 36 — R-MNT-03: el log de auditoría se conserva para siempre.
 * Una entrada no se edita ni se borra, igual que un reporte o un sello
 * (R-TA-02). Lo que está mal se corrige con una entrada nueva, y la
 * anterior queda como estaba.
 */

beforeEach(function () {
    $this->artisan('migrate');
});

afterEach(function () {
    DB::table('audit_logs')->delete();
});

function suspensionEntry(): AuditLog
{
    return AuditLog::record('organization.suspended', null, 'super_admin', '1', 'Root', ['status' => 'active'], ['status' => 'suspended']);
}

it('never lets an entry of the log be edited: it is kept as it was written (R-MNT-03)', function () {
    $entry = suspensionEntry();

    expect(fn () => $entry->update(['after' => ['status' => 'active']]))->toThrow(AuditLogIsAppendOnly::class, 'El log de auditoría no se edita ni se borra: se conserva para siempre.');

    expect(AuditLog::query()->findOrFail($entry->id)->after)->toBe(['status' => 'suspended']);
});

it('never lets an entry of the log be deleted (R-MNT-03)', function () {
    $entry = suspensionEntry();

    expect(fn () => $entry->delete())->toThrow(AuditLogIsAppendOnly::class);

    expect(AuditLog::query()->whereKey($entry->id)->exists())->toBeTrue();
});
