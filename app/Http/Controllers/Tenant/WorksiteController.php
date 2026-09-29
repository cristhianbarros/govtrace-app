<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Contracts\Contract;
use App\Domain\Worksites\Worksite;
use App\Domain\Worksites\WorksiteContract;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * GET /worksites (US-035, it. 18): the organization's worksites with their
 * official location and the contracts they group, so the Administrador
 * finds the one to correct. Contracts live in the central database.
 */
class WorksiteController extends Controller
{
    public function index(): JsonResponse
    {
        $worksites = Worksite::query()->with(['contracts' => fn ($contracts) => $contracts->orderBy('secop_contract_id')])->orderBy('id')->get();
        $objects = Contract::query()
            ->whereIn('secop_contract_id', $worksites->flatMap->contracts->pluck('secop_contract_id'))
            ->pluck('object', 'secop_contract_id');

        return response()->json([
            'data' => $worksites->map(fn (Worksite $worksite) => [
                'id' => $worksite->id,
                'name' => $worksite->name, // US-045-INT: la que agrupa varios contratos
                'latitude' => $worksite->location()?->latitude,
                'longitude' => $worksite->location()?->longitude,
                'contracts' => $worksite->contracts->map(fn (WorksiteContract $link) => [
                    'secop_contract_id' => $link->secop_contract_id,
                    'object' => $objects[$link->secop_contract_id] ?? null,
                ])->all(),
            ]),
        ]);
    }
}
