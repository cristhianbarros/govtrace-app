<?php

namespace App\Http\Controllers\Central;

use App\Application\Audit\AuditFilters;
use App\Application\Audit\AuditLogQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /admin/audit/data (US-043-MON): the whole log, for the Super Administrador. */
class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $audit = (new AuditLogQuery(null));
        $page = $audit->page(AuditFilters::validated($request, true));

        return response()->json([
            'data' => AuditLogQuery::presentAll($page->items()),
            'options' => $audit->options(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }
}
