<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Organization\TerritorySummary;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** GET /summary (US-049-RPT): the Administrador's summary of the territory. The screen is it. 29's. */
class SummaryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => (new TerritorySummary)->handle()]);
    }
}
