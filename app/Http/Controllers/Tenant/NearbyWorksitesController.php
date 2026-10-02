<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Reports\NearbyWorksites;
use App\Domain\Geography\GeoPoint;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /worksites/nearby (US-019): the worksites the veedor has near, for
 * "Nuevo Reporte". The location goes in the body, never in the URL (it. 45f):
 * URLs end up in the access logs of the proxy and the web server.
 */
class NearbyWorksitesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $nearby = (new NearbyWorksites)->handle(new GeoPoint((float) $data['latitude'], (float) $data['longitude']));

        return response()->json(['data' => array_map(fn (array $suggestion) => [
            'worksite_id' => $suggestion['worksite']->public_id, // it. 46c
            'name' => $suggestion['worksite']->name ?? $suggestion['contract']->object,
            'distance_meters' => $suggestion['distance_meters'],
            'contract' => $suggestion['contract']->only(['secop_contract_id', 'object', 'entity_name', 'contractor_name', 'process_number', 'status']),
        ], $nearby)]);
    }
}
