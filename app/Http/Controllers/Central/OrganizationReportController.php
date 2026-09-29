<?php

namespace App\Http\Controllers\Central;

use App\Application\Reports\CreateReportOnBehalf;
use App\Domain\Organization\Exceptions\SuperAdminNotAuthorized;
use App\Domain\Reports\Exceptions\ReportValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * POST /admin/organizations/{id}/reports (US-042-SEC): el Super
 * Administrador crea un reporte en una organización, solo si ella lo
 * autorizó (R-SA-02). El mismo reporte que manda la PWA.
 */
class OrganizationReportController extends Controller
{
    public function store(StoreReportRequest $request, string $tenant): JsonResponse
    {
        $organization = Tenant::query()->findOrFail($tenant);
        $input = $request->toNewReport();

        try {
            $report = $organization->run(fn () => (new CreateReportOnBehalf)->handle($request->user('web'), $input));
        } catch (SuperAdminNotAuthorized $e) {
            abort(403, $e->getMessage());
        } catch (ReportValidationException $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }

        return response()->json(['id' => $report->id], 201);
    }
}
