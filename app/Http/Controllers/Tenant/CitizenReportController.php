<?php

namespace App\Http\Controllers\Tenant;

use App\Application\CitizenReports\CitizenReportDesk;
use App\Application\CitizenReports\CitizenReportRefused;
use App\Application\CitizenReports\ReportedWork;
use App\Domain\CitizenReports\CitizenReportCode;
use App\Domain\Geography\GeoPoint;
use App\Domain\Worksites\Worksite;
use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * POST /citizen-reports/code and /citizen-reports (US-059-LEG): a citizen,
 * without an account, informs the veeduría of what they saw in a worksite —
 * or, since it. 58, in a contract of its territory that has no sheet yet.
 */
class CitizenReportController extends Controller
{
    private const WORK = [
        // It. 46c: el identificador público de la ficha. It. 58: o el contrato, si aún no tiene ficha.
        'worksite_id' => ['nullable', 'required_without:contract_id', 'string', 'exists:tenant.worksites,public_id'],
        'contract_id' => ['nullable', 'required_without:worksite_id', 'string', 'max:100'],
    ];

    public function code(Request $request, CitizenReportDesk $desk): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            ...self::WORK,
            'data_authorization' => ['nullable', 'boolean'],
        ]);

        return $this->refusing(function () use ($desk, $data, $request) {
            $desk->assertReceiving();
            $this->work($data); // it. 58: ningún código para una obra que la veeduría no vigila
            $desk->requestCode($data['email'], $request->boolean('data_authorization'));

            return response()->json(['message' => "Le enviamos un código de 6 dígitos a {$data['email']}. Vence en ".CitizenReportCode::VALID_MINUTES.' minutos.']);
        });
    }

    public function store(Request $request, CitizenReportDesk $desk): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'code' => ['required', 'string'],
            ...self::WORK,
            'message' => ['required', 'string', 'min:20', 'max:2000'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['file'],
            'photo' => ['nullable', 'file'], // una versión anterior de la página envía una sola
            // It. 58: dónde dice el ciudadano que queda una obra sin ubicación (opcional).
            'latitude' => ['nullable', 'required_with:longitude', 'numeric'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric'],
        ], [
            'message.min' => 'Cuéntele a la veeduría qué vio: al menos 20 caracteres.',
            'message.max' => 'El mensaje puede tener máximo 2000 caracteres.',
        ]);

        return $this->refusing(function () use ($desk, $data, $request) {
            $report = $desk->receive(
                $data['email'],
                $data['code'],
                $this->work($data),
                $data['message'],
                array_values(array_filter([...$request->file('photos', []), $request->file('photo')])),
                $this->proposed($data),
            );

            return response()->json(['message' => 'Su informe llegó a la veeduría. Si lo atiende, le responde a su correo.', 'number' => $report->reference()], 201);
        });
    }

    /** @param  array<string, mixed>  $data */
    private function work(array $data): ReportedWork
    {
        return filled($data['worksite_id'] ?? null)
            ? ReportedWork::sheet(Worksite::byPublicId($data['worksite_id']))
            : ReportedWork::contract($data['contract_id']);
    }

    /** @param  array<string, mixed>  $data */
    private function proposed(array $data): ?GeoPoint
    {
        if (! isset($data['latitude'], $data['longitude'])) {
            return null;
        }

        try {
            return new GeoPoint((float) $data['latitude'], (float) $data['longitude']);
        } catch (InvalidArgumentException) {
            throw CitizenReportRefused::invalidLocation();
        }
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
