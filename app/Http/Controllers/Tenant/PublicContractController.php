<?php

namespace App\Http\Controllers\Tenant;

use App\Application\CitizenReports\ReportedWork;
use App\Application\Publication\PublicCache;
use App\Application\Publication\PublicContractView;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * GET /public/contracts/{id} (it. 58, US-059-LEG): a contract of the territory
 * that no veedor has reported yet, for the citizen to inform about it. Only
 * the ones the veeduría watches and may still be reported (R-SEC-07): 404
 * for any other.
 */
class PublicContractController extends Controller
{
    /** The SECOP id of a contract, as it goes in a URL: "CO1.PCCNTR.1234567". */
    public const PATTERN = '[A-Za-z0-9._-]{1,100}';

    public function show(string $contract): JsonResponse
    {
        $found = ReportedWork::reportableContracts()->where('secop_contract_id', $contract)->firstOrFail();

        return response()->json(['data' => PublicCache::remember('contract', ['id' => $found->secop_contract_id], fn () => (new PublicContractView)->handle($found))]);
    }
}
