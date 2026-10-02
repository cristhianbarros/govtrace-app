<?php

namespace App\Http\Controllers\Central;

use App\Application\Sealing\Exceptions\SealingNetworkError;
use App\Application\Sealing\SealingFailures;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Configuration\ConfigurableParameter;
use App\Domain\Configuration\Parameters;
use App\Domain\Sealing\Xlm;
use App\Domain\Shared\PublicId;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenancy\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The sealing screen of the global panel, for the Super Administrador:
 * GET /admin/sealing/data — the sponsor account and the lifetime of the
 * contract, read live from Stellar (US-022), and the seals in "Falla de
 * Sellado" (US-047-MNT); POST /admin/sealing/requeue puts them back in the
 * queue. The failures are listed even when the network doesn't answer.
 */
class SealingController extends Controller
{
    public function data(SealingNetwork $network, SealingFailures $failures): JsonResponse
    {
        try {
            $sponsor = $this->sponsor($network);
            $contract = $this->contract($network);
            $networkError = null;
        } catch (SealingNetworkError) {
            [$sponsor, $contract] = [null, null];
            $networkError = 'No se pudo consultar la red de Stellar. Intente de nuevo en unos minutos.';
        }

        return response()->json([
            'sponsor' => $sponsor,
            'contract' => $contract,
            'network_error' => $networkError,
            'failures' => $failures->all(),
        ]);
    }

    public function requeue(Request $request, SealingFailures $failures): JsonResponse
    {
        $data = $request->validate([
            'seals' => ['required', 'array', 'min:1', 'max:500'],
            'seals.*.organization_id' => ['required', 'string', Rule::exists(Tenant::class, 'id')],
            'seals.*.report_id' => ['required', 'string', 'regex:/^'.PublicId::PATTERN.'$/'], // it. 46c: su identificador público
        ]);

        $requeued = $failures->requeue($data['seals'], $request->user('web'));

        return response()->json([
            'requeued' => $requeued,
            'message' => $requeued === 1 ? '1 evidencia vuelve a la cola de sellado.' : "{$requeued} evidencias vuelven a la cola de sellado.",
        ]);
    }

    /** @return array{address: string, balance_xlm: string, threshold_xlm: string, low: bool} */
    private function sponsor(SealingNetwork $network): array
    {
        $balance = $network->sponsorBalance();
        $threshold = Parameters::current(ConfigurableParameter::SponsorBalanceThreshold->value);

        return [
            'address' => $network->sponsorAddress(),
            'balance_xlm' => Xlm::fromStroops($balance),
            'threshold_xlm' => $threshold,
            'low' => $balance < Xlm::toStroops($threshold),
        ];
    }

    /** @return array{id: string, instance: array{days: int, expires_on: string}, code: array{days: int, expires_on: string}} */
    private function contract(SealingNetwork $network): array
    {
        $lifetime = $network->contractLifetime();
        $now = CarbonImmutable::now();
        $entry = fn (int $liveUntil) => [
            'days' => $lifetime->daysLeft($liveUntil),
            'expires_on' => $lifetime->expiresAt($liveUntil, $now)->timezone('America/Bogota')->toDateString(),
        ];

        return ['id' => $network->contractId(), 'instance' => $entry($lifetime->instanceLiveUntil), 'code' => $entry($lifetime->codeLiveUntil)];
    }
}
