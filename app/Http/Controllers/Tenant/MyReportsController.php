<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Pagination\Page;
use App\Domain\Contracts\Contract;
use App\Domain\Reports\Report;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /me/reports (US-010): los reportes del veedor, solo los suyos
 * (R-VC-02), los más recientes primero. El estado técnico y el editorial van
 * por separado (R-USR-02), en sus palabras: nunca un error de sellado
 * (US-021), y el motivo solo si se rechazó. La pantalla es Veedor/MyReports.
 */
class MyReportsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);

        $query = Report::query()
            ->where('user_id', $request->user('tenant')->id)
            ->with(['seal', 'worksite.contracts'])
            ->orderByDesc('captured_at')
            ->orderByDesc('id');

        // It. 52: de 20 en 20 ("Ver 20 más"), no todos de una vez.
        return response()->json(Page::of($query, (int) $request->query('page', 1), function ($reports) {
            $objects = Contract::query()
                ->whereIn('secop_contract_id', $reports->flatMap(fn (Report $report) => $report->worksite->contracts->pluck('secop_contract_id'))->unique())
                ->pluck('object', 'secop_contract_id');

            return $reports->map(function (Report $report) use ($objects) {
                $editorial = $report->editorialStatusForVeedor();
                $firstContract = $report->worksite->contracts->sortBy('secop_contract_id')->first()?->secop_contract_id;

                return [
                    'id' => $report->public_id, // it. 46c
                    'captured_at' => $report->captured_at->toIso8601String(),
                    'classification' => $report->classification->value,
                    // La ficha completa (R-INT-05): su nombre, o el objeto de su contrato.
                    'worksite' => $report->worksite->name ?? $objects[$firstContract] ?? null,
                    'technical_status' => $report->seal->status->veedorLabel(),
                    'editorial_status' => $editorial['status'],
                    'rejection_reason' => $editorial['reason'],
                    'receipt_url' => "/reports/{$report->public_id}/receipt",
                ];
            });
        }));
    }
}
