<?php

namespace App\Http\Controllers\Central;

use App\Application\Organization\UsageSummary;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** GET /admin/usage/data (US-053-RPT): the usage of each organization, for the Super Administrador. */
class UsageController extends Controller
{
    public function __invoke(UsageSummary $usage): JsonResponse
    {
        return response()->json(['data' => $usage->rows()]);
    }
}
