<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Sealing\SealReceipt;
use App\Domain\Reports\Report;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El Recibo de Inmutabilidad (it. 23; las pantallas, it. 27 y 28).
 *
 * - GET /reports/{id}/receipt (US-023): el veedor, solo de sus reportes —
 *   de uno ajeno, 404, como si no existiera.
 * - GET /public/reports/{id}/receipt (US-025): cualquiera, sin sesión, si
 *   está en la línea de tiempo pública: publicado, o retirado — su sello
 *   sigue disponible para auditoría (US-037). Oculto o rechazado, 404.
 */
class ReceiptController extends Controller
{
    public function mine(Request $request, string $report): JsonResponse
    {
        $sealed = Report::query()->where('user_id', $request->user('tenant')->id)->with('seal')->where('public_id', $report)->firstOrFail();

        return response()->json(['data' => SealReceipt::of($sealed->seal)]);
    }

    public function public(string $report): JsonResponse
    {
        $sealed = Report::query()->onPublicTimeline()->with('seal')->where('public_id', $report)->firstOrFail();

        return response()->json(['data' => SealReceipt::of($sealed->seal)]);
    }
}
