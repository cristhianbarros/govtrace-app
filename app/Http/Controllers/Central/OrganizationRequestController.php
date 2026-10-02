<?php

namespace App\Http\Controllers\Central;

use App\Application\Organization\OrganizationRequests;
use App\Application\Organization\RegistrationDocuments;
use App\Domain\Organization\OrganizationRequest;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * US-062-ALT (it. 43k, V10): a veeduría asks for its alta from the Home
 * (public, limited by connection), and the Super Administrador sees the
 * pending ones and rejects one with a reason. Approving is registering the
 * organization from "Nueva organización", pre-filled (OrganizationController).
 */
class OrganizationRequestController extends Controller
{
    private const AUTHORIZATION_REQUIRED = 'Para enviar la solicitud, autorice el tratamiento de sus datos personales.';

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:200'],
            'contact_email' => ['nullable', 'string', 'max:200'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'registration_authority' => ['nullable', 'string', 'max:150'],
            'data_authorization' => ['accepted'],
            'website' => ['nullable', 'string'], // el campo oculto: solo un robot lo llena
            'document' => ['nullable'], // it. 46b: RegistrationDocuments lo revisa
        ], ['data_authorization.accepted' => self::AUTHORIZATION_REQUIRED]);

        $answer = response()->json(['message' => "Recibimos su solicitud. El equipo de GovTrace la revisará y le escribirá a {$data['contact_email']}."], 201);
        if (filled($data['website'] ?? null)) {
            return $answer; // la misma respuesta, sin guardar nada
        }

        $requests = new OrganizationRequests;
        $problems = $requests->problems($data['name'] ?? null, $data['contact_email'] ?? null, $data['registration_number'] ?? null, $data['registration_authority'] ?? null);
        try {
            $document = RegistrationDocuments::assertAcceptable($request->file('document'));
        } catch (ValidationException) {
            $problems['document'] = RegistrationDocuments::NEEDS_THE_PDF;
        }
        if ($problems !== []) {
            throw ValidationException::withMessages($problems);
        }
        $requests->submit($data['name'], $data['contact_email'], $data['registration_number'], $data['registration_authority'], $document);

        return $answer;
    }

    public function show(): Response
    {
        return Inertia::render('SuperAdmin/OrganizationRequests');
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => (new OrganizationRequests)->pending()]);
    }

    /** It. 46b: lo que dice el RUES de la veeduría de la solicitud. */
    public function rues(string $organizationRequest): JsonResponse
    {
        return response()->json((new OrganizationRequests)->rues(OrganizationRequest::idOf($organizationRequest)));
    }

    /** It. 46b: el PDF que adjuntó, solo para el Super Administrador. */
    public function document(string $organizationRequest): StreamedResponse
    {
        $pending = OrganizationRequest::byPublicId($organizationRequest);

        return RegistrationDocuments::download($pending->document_path, "Inscripción {$pending->name}");
    }

    public function reject(Request $request, string $organizationRequest): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], ['reason.required' => 'Escriba el motivo: le llega a quien pidió el alta.']);

        try {
            $rejected = (new OrganizationRequests)->reject(OrganizationRequest::idOf($organizationRequest), trim($data['reason']), $request->user('web'));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['message' => "Solicitud rechazada. Le escribimos a {$rejected->contact_email} con el motivo."]);
    }
}
