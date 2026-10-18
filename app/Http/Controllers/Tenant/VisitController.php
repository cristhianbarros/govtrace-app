<?php

namespace App\Http\Controllers\Tenant;

use App\Application\CitizenReports\CitizenVisits;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * It. 65b (US-068): the visits the Administrador asked a veedor for, by a
 * citizen report. A veedor only reaches their own pending ones (404 otherwise):
 * the photos of the citizen, and "No puedo ir".
 */
class VisitController extends Controller
{
    /** La foto número $position (de 1 a 3) que envió el ciudadano. */
    public function photo(Request $request, string $report, int $position): StreamedResponse
    {
        $path = CitizenVisits::ofVeedor($request->user('tenant'), $report)->photo_paths[$position - 1] ?? null;
        abort_if($path === null, 404);

        return Storage::disk('evidencias')->response($path, "visita-{$report}-{$position}.jpg", ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store, private']);
    }

    public function unable(Request $request, string $report, CitizenVisits $visits): JsonResponse
    {
        $visits->unable($request->user('tenant'), $report);

        return response()->json(['message' => 'Listo: la veeduría le pedirá la visita a otro veedor.']);
    }
}
