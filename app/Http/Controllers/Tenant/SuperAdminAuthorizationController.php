<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Organization\SuperAdminAuthorizations;
use App\Domain\Organization\Exceptions\SuperAdminAuthorizationRejected;
use App\Domain\Organization\SuperAdminAuthorization;
use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * /authorizations/super-admin (US-042-SEC), del Administrador: ver, otorgar
 * (30 días) o revocar la autorización al Super Administrador para reportar
 * en nombre de la organización. La pantalla es de la it. 29.
 */
class SuperAdminAuthorizationController extends Controller
{
    public function show(): JsonResponse
    {
        $authorization = SuperAdminAuthorization::inForce();

        return response()->json(['data' => [
            'active' => $authorization !== null,
            'granted_at' => $authorization?->granted_at->utc()->toIso8601String(),
            'expires_at' => $authorization?->expires_at->utc()->toIso8601String(),
            'granted_by' => $authorization?->grantor->name,
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $authorization = $this->attempt(fn () => (new SuperAdminAuthorizations)->grant($request->user('tenant')));
        $until = $authorization->expires_at->copy()->timezone('America/Bogota')->format('d/m/Y');

        return response()->json([
            'message' => "El Super Administrador puede crear reportes en nombre de la organización hasta el {$until}.",
            'data' => ['expires_at' => $authorization->expires_at->utc()->toIso8601String()],
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->attempt(fn () => (new SuperAdminAuthorizations)->revoke($request->user('tenant')));

        return response()->json(['message' => 'Autorización revocada. El Super Administrador ya no puede crear reportes en nombre de la organización.']);
    }

    private function attempt(Closure $change): mixed
    {
        try {
            return $change();
        } catch (SuperAdminAuthorizationRejected $e) {
            throw ValidationException::withMessages(['authorization' => $e->getMessage()]);
        }
    }
}
