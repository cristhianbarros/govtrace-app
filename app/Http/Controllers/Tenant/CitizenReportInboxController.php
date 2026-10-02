<?php

namespace App\Http\Controllers\Tenant;

use App\Application\CitizenReports\CitizenReportInbox;
use App\Domain\CitizenReports\CitizenReport;
use App\Http\Controllers\Controller;
use Closure;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The Administrador's "Informes ciudadanos" (US-059-LEG): read, answer, discard. */
class CitizenReportInboxController extends Controller
{
    public function index(CitizenReportInbox $inbox): JsonResponse
    {
        return response()->json(['data' => $inbox->list()]);
    }

    public function photo(string $report): StreamedResponse
    {
        $path = CitizenReport::byPublicId($report)->photo_path;
        abort_if($path === null, 404);

        return Storage::disk('evidencias')->response($path, "informe-{$report}.jpg", ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store, private']);
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
