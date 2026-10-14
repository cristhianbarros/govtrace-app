<?php

namespace App\Http\Controllers\Tenant;

use App\Application\CitizenReports\CitizenReportInbox;
use App\Domain\CitizenReports\CitizenReport;
use App\Domain\Configuration\Parameters;
use App\Http\Controllers\Controller;
use Closure;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The Administrador's "Informes ciudadanos" (US-059-LEG): read, answer, discard. */
class CitizenReportInboxController extends Controller
{
    /** It. 52: de 20 en 20, y por estado (?page=, ?status=new|answered|discarded). */
    public function index(Request $request, CitizenReportInbox $inbox): JsonResponse
    {
        $query = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(array_keys(CitizenReport::STATUS_LABELS))],
        ]);

        return response()->json($inbox->list((int) ($query['page'] ?? 1), $query['status'] ?? null));
    }

    /** It. 46h: la foto número $position (de 1 a 3) del informe. */
    public function photo(string $report, int $position): StreamedResponse
    {
        $path = CitizenReport::byPublicId($report)->photo_paths[$position - 1] ?? null;
        abort_if($path === null, 404);

        return Storage::disk('evidencias')->response($path, "informe-{$report}-{$position}.jpg", ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store, private']);
    }

    public function answer(Request $request, string $report, CitizenReportInbox $inbox): JsonResponse
    {
        $data = $request->validate(['answer' => ['required', 'string', 'min:5', 'max:2000']], ['answer.required' => 'Escriba la respuesta para el ciudadano.']);

        return $this->handling(fn () => $inbox->answer($request->user('tenant'), CitizenReport::idOf($report), $data['answer']), 'Respuesta enviada al ciudadano.');
    }

    public function discard(Request $request, string $report, CitizenReportInbox $inbox): JsonResponse
    {
        return $this->handling(fn () => $inbox->discard($request->user('tenant'), CitizenReport::idOf($report)), 'Informe descartado.');
    }

    /** It. 58 (US-035): la ubicación que propuso el ciudadano pasa a ser la oficial de la obra. */
    public function confirmLocation(Request $request, string $report, CitizenReportInbox $inbox): JsonResponse
    {
        $radiusMeters = Parameters::current('geofence_radius_meters');

        return $this->handling(
            fn () => $inbox->confirmLocation($request->user('tenant'), CitizenReport::idOf($report)),
            "La ubicación de la obra quedó confirmada: ya aparece en el mapa, y la geocerca de {$radiusMeters} m está activa para los veedores.",
        );
    }

    private function handling(Closure $work, string $done): JsonResponse
    {
        try {
            $work();
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['message' => $done]);
    }
}
