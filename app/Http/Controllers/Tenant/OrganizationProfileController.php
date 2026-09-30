<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Organization\UpdateOrganizationProfile;
use App\Domain\Organization\Exceptions\LogoRejected;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\OrganizationLogo;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * GET/POST /organization/profile (US-007, it. 21): the display name and
 * logo of the organization, for its Administrador. The NIT and the legal
 * name come along read-only: this endpoint never changes them.
 */
class OrganizationProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $tenant = tenant();

        return response()->json(['data' => [
            'display_name' => $tenant->displayName(),
            'legal_name' => $tenant->name,
            'nit' => $tenant->nit,
            'registration_number' => $tenant->registration_number,
            'registration_authority' => $tenant->registration_authority,
            'subdomain' => $tenant->domains()->first()?->domain,
            'logo_url' => $tenant->logoUrl(),
        ]]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'display_name' => ['nullable', 'string'],
            'logo' => ['nullable', 'file'],
        ]);

        try {
            $logo = $request->hasFile('logo') ? OrganizationLogo::fromFile($request->file('logo')->getRealPath()) : null;
        } catch (LogoRejected $e) {
            throw ValidationException::withMessages(['logo' => $e->getMessage()]);
        }

        try {
            (new UpdateOrganizationProfile)->handle($request->user('tenant'), $request->input('display_name'), $logo);
        } catch (OrganizationValidationException $e) {
            throw ValidationException::withMessages(['display_name' => $e->getMessage()]);
        }

        return response()->json(['message' => 'Los cambios fueron guardados. Sus veedores ya ven el nuevo nombre y logo.']);
    }
}
