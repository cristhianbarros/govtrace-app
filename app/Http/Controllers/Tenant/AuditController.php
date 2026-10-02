<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Audit\AuditFilters;
use App\Application\Audit\AuditLogQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /audit and /audit/{id} (US-043-MON): the log of THIS organization,
 * for its Administrador. An entry of another one answers 404.
 */
class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $audit = (new AuditLogQuery(tenant()->getTenantKey()));
        $page = $audit->page(AuditFilters::validated($request, false));

        return response()->json([
            'data' => AuditLogQuery::presentAll($page->items()),
            'options' => $audit->options(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(int $entry): JsonResponse
    {
        $found = (new AuditLogQuery(tenant()->getTenantKey()))->find($entry);

        return response()->json(['data' => AuditLogQuery::presentAll([$found])[0]]);
    }
}
