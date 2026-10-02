<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Reports\CreateReport;
use App\Domain\Reports\Exceptions\ReportValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * POST /reports (US-008) — the PWA's report endpoint. A rejection comes
 * back as a 422 under the field it is about.
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

        return response()->json(['id' => $report->public_id], 201); // it. 46c
    }
}
