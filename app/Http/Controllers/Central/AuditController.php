<?php

namespace App\Http\Controllers\Central;

use App\Application\Audit\AuditLogQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** GET /admin/audit/data (US-043-MON): the whole log, for the Super Administrador. */
class AuditController extends Controller
{
    public function index(): JsonResponse
    {
        $page = (new AuditLogQuery(null))->page();

        return response()->json([
            'data' => AuditLogQuery::presentAll($page->items()),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }
}
