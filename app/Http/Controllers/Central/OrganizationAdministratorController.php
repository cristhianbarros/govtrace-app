<?php

namespace App\Http\Controllers\Central;

use App\Application\Organization\OrganizationAdministrators;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenancy\Tenant;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * It. 43a (V2): el Administrador de una organización, desde el panel global —
 * asignarlo si no tiene, y reenviar o revocar su invitación sin responder.
 */
class OrganizationAdministratorController extends Controller
{
    public function store(Request $request, string $tenant): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'max:190'],
        ]);

        return $this->answer(function () use ($tenant, $data) {
            $hours = (new OrganizationAdministrators)->assign(Tenant::query()->findOrFail($tenant), trim($data['name']), trim($data['email']));

            return response()->json(['message' => "Invitación enviada a {$data['email']}. El enlace vence en {$hours} horas."], 201);
        });
    }

    public function resend(Request $request, string $tenant, string $user): JsonResponse
    {
        return $this->answer(function () use ($request, $tenant, $user) {
            $organization = Tenant::query()->findOrFail($tenant);
            $administrators = new OrganizationAdministrators;
            $hours = $administrators->resend($organization, $user, $request->user('web'));
            $email = collect($administrators->of($organization))->firstWhere('id', $user)['email'];

            return response()->json(['message' => "Invitación reenviada a {$email}. El nuevo enlace vence en {$hours} horas."]);
        });
    }

    public function revoke(Request $request, string $tenant, string $user): JsonResponse
    {
        return $this->answer(function () use ($request, $tenant, $user) {
            $email = (new OrganizationAdministrators)->revoke(Tenant::query()->findOrFail($tenant), $user, $request->user('web'));

            return response()->json(['message' => "Invitación revocada. El enlace enviado a {$email} ya no es válido."]);
        });
    }

    /** It. 43j (V3): an Administrador who left can no longer enter; never the only active one. */
    public function deactivate(Request $request, string $tenant, string $user): JsonResponse
    {
        return $this->answer(function () use ($request, $tenant, $user) {
            (new OrganizationAdministrators)->deactivate(Tenant::query()->findOrFail($tenant), $user, $request->user('web'));

            return response()->json(['message' => 'Administrador desactivado. Su sesión quedó cerrada y ya no puede entrar.']);
        });
    }

    public function reactivate(Request $request, string $tenant, string $user): JsonResponse
    {
        return $this->answer(function () use ($request, $tenant, $user) {
            (new OrganizationAdministrators)->reactivate(Tenant::query()->findOrFail($tenant), $user, $request->user('web'));

            return response()->json(['message' => 'Administrador reactivado. Ya puede entrar otra vez.']);
        });
    }

    private function answer(callable $work): JsonResponse
    {
        try {
            return $work();
        } catch (OrganizationValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['email' => [$e->getMessage()]]], 422);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }
}
