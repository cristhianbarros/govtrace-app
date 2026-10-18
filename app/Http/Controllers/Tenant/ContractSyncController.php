<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Contracts\SecopSyncRun;
use App\Http\Controllers\Controller;
use App\Jobs\SyncSecopContracts;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * It. 63 (US-070): the Administrador asks for the SECOP II sync of its own
 * territory without waiting for the night — the same job, with the id of its
 * organization, as when it was registered. Once every 5 minutes per
 * organization (the cache key carries its prefix), not to hammer the API of
 * datos.gov.co. The screen shows how the last run that covered its territory
 * went: the nightly one (for everyone) or its own.
 */
class ContractSyncController extends Controller
{
    private const PAUSE_SECONDS = 300;

    public function show(): JsonResponse
    {
        $id = tenant()->getTenantKey();
        $run = SecopSyncRun::query()->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $id))->latest('id')->first();

        return response()->json(['data' => $run === null ? null : [
            'status' => $run->status, // running | success | failed
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'inserted' => (int) ($run->per_organization[$id]['inserted'] ?? 0),
            'updated' => (int) ($run->per_organization[$id]['updated'] ?? 0),
        ]]);
    }

    public function store(): JsonResponse
    {
        if (! Cache::add('secop.sync-now', true, self::PAUSE_SECONDS)) {
            return response()->json(['message' => 'Ya pidió una sincronización hace menos de 5 minutos. Espere su resultado.'], 429);
        }

        SyncSecopContracts::dispatch(tenant()->getTenantKey());

        return response()->json(['message' => 'Buscamos los contratos de su territorio en el SECOP II. En unos minutos verá el resultado aquí.'], 202);
    }
}
