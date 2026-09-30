<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Jobs\SyncSecopContracts;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * It. 43b (V15 de docs/mapa-funcional.md): el Super Administrador pide la
 * sincronización con SECOP II sin esperar a la madrugada, por ejemplo tras una
 * caída de la API. Una a la vez: otra pedida en los 5 minutos siguientes
 * espera, para no martillar la API de datos.gov.co. El resultado se ve en la
 * salud de SECOP (US-014), como el de la corrida de cada noche.
 */
class SecopSyncNowController extends Controller
{
    private const PAUSE_SECONDS = 300;

    public function __invoke(): JsonResponse
    {
        if (! Cache::add('secop.sync-now', true, self::PAUSE_SECONDS)) {
            return response()->json(['message' => 'Ya se pidió una sincronización hace menos de 5 minutos. Espere su resultado.'], 429);
        }

        SyncSecopContracts::dispatch();

        return response()->json(['message' => 'Sincronización con SECOP II en marcha. En unos minutos verá el resultado aquí.'], 202);
    }
}
