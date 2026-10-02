<?php

namespace App\Http\Controllers\Central;

use App\Application\Contracts\SearchSelectableContracts;
use App\Application\Reports\CreateReportOnBehalf;
use App\Domain\Contracts\Contract;
use App\Domain\Organization\Exceptions\SuperAdminNotAuthorized;
use App\Domain\Organization\SuperAdminAuthorization;
use App\Domain\Reports\Exceptions\ReportValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * US-042-SEC: el Super Administrador reporta en nombre de una organización,
 * solo si ella lo autorizó (R-SA-02). It. 43g (V7): la pantalla y la
 * búsqueda de la obra en el territorio de esa organización; el envío es el
 * mismo reporte que manda la PWA.
 */
class OrganizationReportController extends Controller
{
    /** GET /admin/organizations/{id}/report: la pantalla, con hasta cuándo vale la autorización. */
    public function create(string $tenant): Response
    {
        $organization = Tenant::query()->findOrFail($tenant);
        $until = $this->authorizedUntil($organization);

        return Inertia::render('SuperAdmin/ReportOnBehalf', [
            'organization' => ['id' => $organization->id, 'name' => $organization->name],
            'authorizedUntil' => $until,
            'refusal' => $until ? null : (new SuperAdminNotAuthorized)->getMessage(),
        ]);
    }

    /** GET /admin/organizations/{id}/contracts/search?q=: "Buscar Obra", en su territorio (US-016). */
    public function contracts(Request $request, string $tenant): JsonResponse
    {
        $organization = Tenant::query()->findOrFail($tenant);
        abort_unless($this->authorizedUntil($organization), 403, (new SuperAdminNotAuthorized)->getMessage());

        $contracts = $organization->run(fn () => (new SearchSelectableContracts)->handle(tenant(), $request->string('q')->toString()));

        return response()->json([
            'data' => $contracts->map(fn (Contract $contract) => $contract->only([
                'secop_contract_id', 'object', 'entity_name', 'contractor_name', 'process_number', 'status',
            ]))->values(),
        ]);
    }

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

        return response()->json(['id' => $report->public_id], 201); // it. 46c
    }

    private function authorizedUntil(Tenant $organization): ?string
    {
        return $organization->run(fn () => SuperAdminAuthorization::inForce()?->expires_at->toIso8601String());
    }
}
