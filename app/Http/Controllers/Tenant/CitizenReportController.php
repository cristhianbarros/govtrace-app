<?php

namespace App\Http\Controllers\Tenant;

use App\Application\CitizenReports\CitizenReportDesk;
use App\Application\CitizenReports\CitizenReportRefused;
use App\Domain\CitizenReports\CitizenReportCode;
use App\Domain\Worksites\Worksite;
use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * POST /citizen-reports/code and /citizen-reports (US-059-LEG): a citizen,
 * without an account, informs the veeduría of what they saw in a worksite.
 */
class CitizenReportController extends Controller
{
    public function code(Request $request, CitizenReportDesk $desk): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'worksite_id' => ['required', 'string', 'exists:tenant.worksites,public_id'], // it. 46c: su identificador público
            'data_authorization' => ['nullable', 'boolean'],
        ]);

        return $this->refusing(function () use ($desk, $data, $request) {
            $desk->requestCode($data['email'], $request->boolean('data_authorization'));

            return response()->json(['message' => "Le enviamos un código de 6 dígitos a {$data['email']}. Vence en ".CitizenReportCode::VALID_MINUTES.' minutos.']);
        });
    }

    public function store(Request $request, CitizenReportDesk $desk): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'code' => ['required', 'string'],
            'worksite_id' => ['required', 'string', 'exists:tenant.worksites,public_id'], // it. 46c: su identificador público
            'message' => ['required', 'string', 'min:20', 'max:2000'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['file'],
            'photo' => ['nullable', 'file'], // una versión anterior de la página envía una sola
        ], [
            'message.min' => 'Cuéntele a la veeduría qué vio: al menos 20 caracteres.',
            'message.max' => 'El mensaje puede tener máximo 2000 caracteres.',
        ]);

        return $this->refusing(function () use ($desk, $data, $request) {
            $report = $desk->receive($data['email'], $data['code'], Worksite::byPublicId($data['worksite_id']), $data['message'], array_values(array_filter([...$request->file('photos', []), $request->file('photo')])));

            return response()->json(['message' => 'Su informe llegó a la veeduría. Si lo atiende, le responde a su correo.', 'number' => $report->reference()], 201);
        });
    }

    private function refusing(Closure $work): JsonResponse
    {
        try {
            return $work();
        } catch (CitizenReportRefused $refused) {
            if ($refused->field) {
                throw ValidationException::withMessages([$refused->field => $refused->getMessage()]);
            }

            return response()->json(['message' => $refused->getMessage()], $refused->status);
        }
    }
}
