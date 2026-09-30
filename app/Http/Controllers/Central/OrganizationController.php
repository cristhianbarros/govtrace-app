<?php

namespace App\Http\Controllers\Central;

use App\Application\Organization\DecommissionOrganization;
use App\Application\Organization\OrganizationAdministrators;
use App\Application\Organization\ReactivateOrganization;
use App\Application\Organization\RegisterOrganizationWithAdministrator;
use App\Application\Organization\SuspendOrganization;
use App\Application\Organization\UpdateOrganizationLegalData;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The Super Administrador's panel (it. 19): the organizations list, the
 * alta form (US-001 + US-002 in one step) and the legal-data screen
 * (US-011). The rules live in their Actions; this only maps HTTP.
 */
class OrganizationController extends Controller
{
    public function index(): JsonResponse
    {
        $administrators = new OrganizationAdministrators;

        return response()->json([
            'data' => Tenant::query()->orderBy('name')->get()
                ->map(fn (Tenant $tenant) => [
                    'id' => $tenant->id,
                    'nit' => $tenant->nit,
                    'name' => $tenant->name,
                    'subdomain' => $tenant->domains()->first()?->domain,
                    'status' => $tenant->statusLabel(),
                    // It. 43a (V16): quién la administra y en qué va su invitación. Una
                    // dada de baja ya no tiene su base de datos de usuarios.
                    'administrators' => $tenant->status === OrganizationStatus::Decommissioned->value ? [] : $administrators->of($tenant),
                ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'nit' => ['required', 'string'],
            'subdomain' => ['required', 'string'],
            'administrator_name' => ['nullable', 'string', 'required_with:administrator_email'],
            'administrator_email' => ['nullable', 'string', 'required_with:administrator_name'],
        ]);

        try {
            $tenant = (new RegisterOrganizationWithAdministrator)->handle(
                $data['nit'],
                $data['name'],
                $data['subdomain'],
                $data['administrator_name'] ?? null,
                $data['administrator_email'] ?? null,
            );
        } catch (OrganizationValidationException $e) {
            throw ValidationException::withMessages([$this->fieldFor($e) => $e->getMessage()]);
        }

        $domain = $tenant->domains()->first()->domain;

        return response()->json(['message' => "Organización registrada. El subdominio {$domain} ya está activo."], 201);
    }

    public function show(string $tenant): JsonResponse
    {
        $organization = Tenant::query()->findOrFail($tenant);

        return response()->json(['data' => [
            'id' => $organization->id,
            'name' => $organization->name,
            'nit' => $organization->nit,
            'subdomain' => $organization->domains()->first()?->domain,
            'status' => $organization->statusLabel(),
        ]]);
    }

    public function updateNit(Request $request, string $tenant): JsonResponse
    {
        $data = $request->validate(['nit' => ['required', 'string']]);
        $organization = Tenant::query()->findOrFail($tenant);

        try {
            (new UpdateOrganizationLegalData)->handle($organization, $data['nit']);
        } catch (OrganizationValidationException $e) {
            throw ValidationException::withMessages(['nit' => $e->getMessage()]);
        }

        return response()->json(['message' => 'El NIT ha sido actualizado.']);
    }

    /** US-003a */
    public function suspend(string $tenant): JsonResponse
    {
        return $this->changeStatus(
            fn (Tenant $organization) => (new SuspendOrganization)->handle($organization),
            $tenant,
            'Organización suspendida. Sus usuarios ya no pueden entrar; su mapa público sigue disponible.',
        );
    }

    /** US-003a */
    public function reactivate(string $tenant): JsonResponse
    {
        return $this->changeStatus(
            fn (Tenant $organization) => (new ReactivateOrganization)->handle($organization),
            $tenant,
            'Organización reactivada. Sus usuarios ya pueden volver a entrar.',
        );
    }

    /** US-003b: the first confirmation — what the decommission implies, and the token for the second. */
    public function startDecommission(string $tenant): JsonResponse
    {
        try {
            $first = (new DecommissionOrganization)->start(Tenant::query()->findOrFail($tenant));
        } catch (OrganizationValidationException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return response()->json([
            ...$first,
            'message' => "Para confirmar la baja definitiva, escriba el subdominio de la organización: {$first['summary']['subdomain']}.",
        ]);
    }

    /** US-003b: the second confirmation, with the subdomain typed. */
    public function decommission(Request $request, string $tenant): JsonResponse
    {
        $data = $request->validate([
            'token' => ['nullable', 'string'],
            'subdomain' => ['required', 'string'],
        ]);

        try {
            (new DecommissionOrganization)->confirm(Tenant::query()->findOrFail($tenant), $data['token'] ?? null, $data['subdomain']);
        } catch (OrganizationValidationException $e) {
            throw ValidationException::withMessages([match (true) {
                str_contains($e->getMessage(), 'confirmación') => 'token',
                str_contains($e->getMessage(), 'subdominio') => 'subdomain',
                default => 'status',
            } => $e->getMessage()]);
        }

        return response()->json(['message' => 'Organización dada de baja. Sus usuarios ya no tienen acceso; su mapa salió de línea y sus evidencias siguen verificables.']);
    }

    private function changeStatus(callable $change, string $tenant, string $message): JsonResponse
    {
        try {
            $change(Tenant::query()->findOrFail($tenant));
        } catch (OrganizationValidationException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return response()->json(['message' => $message]);
    }

    /** Which form field an OrganizationValidationException is about, for the alta screen. */
    private function fieldFor(OrganizationValidationException $e): string
    {
        return match (true) {
            str_contains($e->getMessage(), 'NIT') => 'nit',
            str_contains($e->getMessage(), 'subdominio') => 'subdomain',
            str_contains($e->getMessage(), 'nombre de la organización') => 'name',
            str_contains($e->getMessage(), 'correo electrónico') => 'administrator_email',
            default => 'name',
        };
    }
}
