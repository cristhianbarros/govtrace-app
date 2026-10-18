<?php

namespace App\Http\Controllers\Tenant;

use App\Application\CitizenReports\CitizenReportRefused;
use App\Application\CitizenReports\ReportedWork;
use App\Application\Places\PlaceSearch;
use App\Application\Places\PlaceSearchUnavailable;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * It. 68 (US-059-LEG, US-035): GET /public/places — where a work is, by its
 * address or a place nearby, for the citizen who marks a work without
 * location and the Administrador who corrects one. The same work fields as a
 * citizen report: a sheet or a contract the veeduría watches.
 */
final class PlaceSearchController extends Controller
{
    public function __invoke(Request $request, PlaceSearch $places): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:120'],
            ...CitizenReportController::WORK,
        ], [
            'q.required' => 'Escriba al menos 3 letras del lugar.',
            'q.min' => 'Escriba al menos 3 letras del lugar.',
            'q.max' => 'Escriba hasta 120 caracteres.',
        ]);

        try {
            return response()->json(['data' => $places->find($data['q'], ReportedWork::requested($data))]);
        } catch (CitizenReportRefused $refused) {
            throw ValidationException::withMessages([$refused->field ?? 'q' => $refused->getMessage()]);
        } catch (PlaceSearchUnavailable $unavailable) {
            return response()->json(['message' => $unavailable->getMessage()], 503);
        }
    }
}
