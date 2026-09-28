<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Organization\DeactivateObserver;
use App\Application\Organization\ReactivateObserver;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The veedores of the organization, for the Administrador's panel: the team
 * with the status of each one (US-005, it. 18), and deactivating or
 * reactivating them (US-006, US-041-USR, it. 20). Only veedores: an
 * Administrador is not found here.
 */
class ObserverController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => User::role(Roles::Observer->value, 'tenant')->orderBy('email')->get()
                ->map(fn (User $observer) => ['id' => $observer->id, 'email' => $observer->email, 'status' => $observer->statusLabel()])
                ->values(),
        ]);
    }

    public function deactivate(Request $request, int $observer): JsonResponse
    {
        return $this->change(
            fn () => (new DeactivateObserver)->handle($request->user('tenant'), $this->observer($observer)),
            'Veedor desactivado. Su sesión quedó cerrada y ya no puede enviar reportes.',
        );
    }

    public function reactivate(Request $request, int $observer): JsonResponse
    {
        return $this->change(
            fn () => (new ReactivateObserver)->handle($request->user('tenant'), $this->observer($observer)),
            'Veedor reactivado. Ya puede volver a iniciar sesión.',
        );
    }

    private function observer(int $id): User
    {
        return User::role(Roles::Observer->value, 'tenant')->findOrFail($id);
    }

    private function change(callable $change, string $message): JsonResponse
    {
        try {
            $change();
        } catch (OrganizationValidationException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return response()->json(['message' => $message]);
    }
}
