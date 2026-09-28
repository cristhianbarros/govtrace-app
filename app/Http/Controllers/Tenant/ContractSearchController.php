<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Contracts\SearchSelectableContracts;
use App\Domain\Contracts\Contract;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /contracts/search?q= (US-016) — "Buscar Obra" in the PWA (it. 16).
 * The rules of what can be selected are SearchSelectableContracts'.
 */
class ContractSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $contracts = (new SearchSelectableContracts)->handle(tenant(), $request->string('q')->toString());

        return response()->json([
            'data' => $contracts->map(fn (Contract $contract) => $contract->only([
                'secop_contract_id', 'object', 'entity_name', 'contractor_name', 'process_number', 'status',
            ]))->values(),
        ]);
    }
}
