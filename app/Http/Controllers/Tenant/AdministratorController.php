<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Organization\InviteAdministrator;
use App\Application\Organization\OrganizationAdministrators;
use App\Domain\Configuration\Parameters;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * It. 43j (V3, US-061-USR): the administrators of the organization, for each
 * of them — who they are and how their invitation goes — and inviting
 * another one. Deactivating one is the Super Administrador's.
 */
class AdministratorController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => (new OrganizationAdministrators)->of(tenant())]);
    }

    public function invite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'max:190'],
        ]);

        try {
            (new InviteAdministrator)->handle($request->user('tenant'), trim($data['name']), trim($data['email']));
        } catch (OrganizationValidationException $e) {
            throw ValidationException::withMessages(['email' => $e->getMessage()]);
        }

        $hours = (int) (Parameters::current('invitation_validity_hours') ?? 48);

        return response()->json(['message' => "Invitación enviada a {$data['email']}. El enlace vence en {$hours} horas."], 201);
    }
}
