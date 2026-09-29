<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Worksites\GroupWorksiteContracts;
use App\Domain\Worksites\Exceptions\WorksiteGroupingRejected;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * POST /worksites/group (US-045-INT): the Administrador groups contracts of
 * the organization's territory in one worksite (R-INT-05).
 */
class WorksiteGroupController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $secopContractIds = array_values(array_filter((array) $request->input('secop_contract_ids', []), 'is_string'));

        try {
            $worksite = (new GroupWorksiteContracts)->handle($request->user('tenant'), (string) $request->input('name', ''), $secopContractIds);
        } catch (WorksiteGroupingRejected $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }

        return response()->json(['data' => [
            'id' => $worksite->id,
            'name' => $worksite->name,
            'secop_contract_ids' => $worksite->contracts()->orderBy('secop_contract_id')->pluck('secop_contract_id')->all(),
        ]], 201);
    }
}
