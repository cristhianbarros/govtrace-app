<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Geography\SearchTerritories;
use App\Application\Organization\ConfigureTerritory;
use App\Domain\Geography\Department;
use App\Domain\Geography\Municipality;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\OrganizationTerritory;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The territory of the organization (US-012), from the Administrador's
 * panel (it. 18): what it watches today, the DIVIPOLA picker and saving
 * it. The rules are ConfigureTerritory's.
 */
class TerritoryController extends Controller
{
    public function show(): JsonResponse
    {
        $territory = OrganizationTerritory::query()->where('tenant_id', tenant()->getTenantKey())->get();

        $departments = Department::query()->whereIn('code', $territory->pluck('department_code')->filter())->orderBy('name')->get()
            ->map(fn (Department $department) => SearchTerritories::department($department));
        $municipalities = Municipality::query()->with('department')->whereIn('code', $territory->pluck('municipality_code')->filter())->orderBy('name')->get()
            ->map(fn (Municipality $municipality) => SearchTerritories::municipality($municipality));

        return response()->json(['data' => $departments->concat($municipalities)->values()]);
    }

    public function search(Request $request): JsonResponse
    {
        return response()->json(['data' => (new SearchTerritories)->handle($request->string('q')->toString())]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'codes' => ['present', 'array'],
            'codes.*' => ['string'],
        ]);

        try {
            (new ConfigureTerritory)->handle(tenant(), array_values($data['codes']), $request->user('tenant'));
        } catch (OrganizationValidationException $e) {
            throw ValidationException::withMessages(['codes' => $e->getMessage()]);
        }

        return response()->json(['message' => 'Territorio guardado. Los contratos de SECOP II se están actualizando.']);
    }
}
