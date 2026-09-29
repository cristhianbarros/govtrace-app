<?php

namespace App\Http\Controllers\Central;

use App\Application\Sealing\SealingCosts;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** GET /admin/costs/data (US-004): the sealing fees by month and organization, for the Super Administrador. */
class SealingCostsController extends Controller
{
    public function __invoke(SealingCosts $costs): JsonResponse
    {
        return response()->json($costs->report());
    }
}
