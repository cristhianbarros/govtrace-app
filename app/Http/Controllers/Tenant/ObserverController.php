<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Organization\DeactivateObserver;
use App\Application\Organization\ReactivateObserver;
use App\Application\Organization\ResendInvitation;
use App\Application\Organization\RevokeInvitation;
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
 * reactivating them (US-006, US-041-USR, it. 20), and resending or revoking
 * a pending invitation (US-040-USR, it. 33). Only veedores: an
 * Administrador is not found here.
 */
class ObserverController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => User::role(Roles::Observer->value, 'tenant')->orderBy('email')->get()
                ->map(fn (User $observer) => [
                    'id' => $observer->public_id, // it. 46c
                    'email' => $observer->email,
                    'status' => $observer->statusLabel(),
                    // US-057-LEG: cuándo declaró no tener impedimentos para ser veedor; null, aún no.
                    'impediments_declared_at' => $observer->impediments_declared_at?->toIso8601String(),
                ])
                ->values(),
        ]);
    }

    public function deactivate(Request $request, string $observer): JsonResponse
    {
        return $this->change(function () use ($request, $observer) {
            (new DeactivateObserver)->handle($request->user('tenant'), $this->observer($observer));

            return 'Veedor desactivado. Su sesión quedó cerrada y ya no puede enviar reportes.';
        });
    }

    public function reactivate(Request $request, string $observer): JsonResponse
    {
        return $this->change(function () use ($request, $observer) {
            (new ReactivateObserver)->handle($request->user('tenant'), $this->observer($observer));

            return 'Veedor reactivado. Ya puede volver a iniciar sesión.';
        });
    }

    /** US-040-USR */
    public function resendInvitation(Request $request, string $observer): JsonResponse
    {
        return $this->change(function () use ($request, $observer) {
            $invited = $this->observer($observer);
            $hours = (new ResendInvitation)->handle($request->user('tenant'), $invited);

            return "Invitación reenviada a {$invited->email}. El nuevo enlace vence en {$hours} horas.";
        });
    }

    /** US-040-USR */
    public function revokeInvitation(Request $request, string $observer): JsonResponse
    {
        return $this->change(function () use ($request, $observer) {
            $invited = $this->observer($observer);
            (new RevokeInvitation)->handle($request->user('tenant'), $invited);

            return "Invitación revocada. El enlace enviado a {$invited->email} ya no es válido.";
        });
    }

    private function observer(string $publicId): User
    {
        return User::role(Roles::Observer->value, 'tenant')->where('public_id', $publicId)->firstOrFail();
    }

    /** @param  callable(): string  $change  the change, returning what to tell the Administrador */
    private function change(callable $change): JsonResponse
    {
        try {
            $message = $change();
        } catch (OrganizationValidationException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return response()->json(['message' => $message]);
    }
}
