<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Reports\CreateReport;
use App\Domain\Reports\Exceptions\ReportValidationException;
use App\Domain\Worksites\PendingLocation;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * POST /reports (US-008) — the PWA's report endpoint. A rejection comes
 * back as a 422 under the field it is about. Accepted, it says why the
 * location of its worksite was left to confirm, if it was (it. 46f).
 */
class ReportController extends Controller
{
    public function store(StoreReportRequest $request): JsonResponse
    {
        try {
            $report = (new CreateReport)->handle($request->user('tenant'), $request->toNewReport());
        } catch (ReportValidationException $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }

        // It. 46f: si el primer reporte de la obra no fijó su ubicación, por qué; si no, solo el id.
        $pending = PendingLocation::messageFor($report);

        return response()->json([
            'id' => $report->public_id, // it. 46c
            ...($pending === null ? [] : ['location_pending' => $pending]),
        ], 201);
    }
}
