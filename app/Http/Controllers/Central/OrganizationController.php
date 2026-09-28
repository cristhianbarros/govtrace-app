<?php

namespace App\Http\Controllers\Central;

use App\Application\Organization\RegisterOrganizationWithAdministrator;
use App\Application\Organization\UpdateOrganizationLegalData;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
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
        return response()->json([
            'data' => Tenant::query()->orderBy('name')->get()
                ->map(fn (Tenant $tenant) => [
                    'id' => $tenant->id,
                    'nit' => $tenant->nit,
                    'name' => $tenant->name,
                    'subdomain' => $tenant->domains()->first()?->domain,
                    'status' => $tenant->statusLabel(),
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
