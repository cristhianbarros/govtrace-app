<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Organization\Roles;
use App\Domain\Organization\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * GET /observers (US-005, it. 18): the veedores of the organization, with
 * the status of their invitation, for the Administrador's panel.
 */
class ObserverController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => User::role(Roles::Observer->value, 'tenant')->orderBy('email')->get()
                ->map(fn (User $observer) => ['email' => $observer->email, 'status' => $observer->statusLabel()])
                ->values(),
        ]);
    }
}
