<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Contracts\ListTerritoryContracts;
use App\Domain\Contracts\Contract;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /contracts?sort=signed_at|value&direction=desc|asc&page=&q= (US-015):
 * the contracts of the territory, 20 per page, for the Administrador's
 * panel (it. 18). Any other sort falls back to the default: signing date,
 * newest first.
 */
class ContractListController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $sort = in_array($request->query('sort'), ['signed_at', 'value'], true) ? $request->query('sort') : 'signed_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $page = (new ListTerritoryContracts)->handle(tenant(), $sort, $direction, $request->string('q')->limit(100, '')->toString());

        return response()->json([
            'data' => collect($page->items())->map(fn (Contract $contract) => [
                'secop_contract_id' => $contract->secop_contract_id,
                'process_number' => $contract->process_number,
                'object' => $contract->object,
                'contractor_name' => $contract->contractor_name,
                'value' => $contract->value === null ? null : (float) $contract->value,
                'status' => $contract->status,
                'signed_at' => $contract->signed_at?->toDateString(),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }
}
