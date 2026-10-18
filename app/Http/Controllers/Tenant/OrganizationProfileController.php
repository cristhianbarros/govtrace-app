<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Organization\UpdateOrganizationProfile;
use App\Domain\Organization\Exceptions\ContactRejected;
use App\Domain\Organization\Exceptions\LogoRejected;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\OrganizationContact;
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
            // It. 43h (V13): su contacto público.
            'contact_email' => $tenant->contact_email,
            'contact_phone' => $tenant->contact_phone,
            // It. 66a (R-LEG-12): en cuántos días hábiles responde a los ciudadanos.
            'citizen_response_days' => $tenant->citizenResponseDays(),
        ]]);
    }

    public function update(Request $request): JsonResponse
    {
        $term = 'El plazo va de 1 a 60 días hábiles.';
        $request->validate([
            'display_name' => ['nullable', 'string'],
            'logo' => ['nullable', 'file'],
            'contact_email' => ['nullable', 'string'],
            'contact_phone' => ['nullable', 'string'],
            'citizen_response_days' => ['nullable', 'integer', 'min:1', 'max:60'],
        ], ['citizen_response_days.integer' => $term, 'citizen_response_days.min' => $term, 'citizen_response_days.max' => $term]);

        try {
            // It. 43h (V13): sin los campos, el contacto no cambia; vacíos, se quita.
            $contact = $request->hasAny(['contact_email', 'contact_phone'])
                ? OrganizationContact::from($request->input('contact_email'), $request->input('contact_phone'))
                : null;
        } catch (ContactRejected $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }

        try {
            $logo = $request->hasFile('logo') ? OrganizationLogo::fromFile($request->file('logo')->getRealPath()) : null;
        } catch (LogoRejected $e) {
            throw ValidationException::withMessages(['logo' => $e->getMessage()]);
        }

        try {
            $days = $request->filled('citizen_response_days') ? (int) $request->input('citizen_response_days') : null;
            (new UpdateOrganizationProfile)->handle($request->user('tenant'), $request->input('display_name'), $logo, $contact, $days);
        } catch (OrganizationValidationException $e) {
            throw ValidationException::withMessages(['display_name' => $e->getMessage()]);
        }

        return response()->json(['message' => 'Los cambios fueron guardados. Sus veedores ya ven el nuevo nombre y logo.']);
    }
}
