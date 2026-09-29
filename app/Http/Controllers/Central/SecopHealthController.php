<?php

namespace App\Http\Controllers\Central;

use App\Application\Contracts\SecopHealth;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** GET /admin/secop-health/data (US-014): the last sync, for the Super Administrador. */
class SecopHealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => (new SecopHealth)->latest()]);
    }
}
