<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Contracts\BrowseReportableContracts;
use App\Domain\Contracts\WorkType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * POST /contracts/browse (it. 47a, US-016, US-019): the works the veedor sees
 * on opening "Nuevo reporte" — of his municipality, filtered, overdue first,
 * 20 at a time. His location goes in the body, never in the URL (it. 45f):
 * URLs end up in the access logs of the proxy and the web server. It is used
 * to choose his municipality and the nearby works, and not kept.
 */
class BrowseContractsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'municipality' => ['nullable', 'string', 'regex:/^(\d{5}|dep:\d{2})$/'],
            'work_type' => ['nullable', Rule::enum(WorkType::class)],
            'situation' => ['nullable', Rule::in(BrowseReportableContracts::SITUATIONS)],
            'entity' => ['nullable', 'string', 'max:255'],
            'q' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', Rule::in(['municipality', 'territory'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        return response()->json((new BrowseReportableContracts)->handle(tenant(), $data));
    }
}
