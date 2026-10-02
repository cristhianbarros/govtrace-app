<?php

namespace App\Application\Organization;

use App\Application\Privacy\DataPolicy;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Exceptions\ContactRejected;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\Notifications\OrganizationRequestRejected;
use App\Domain\Organization\OrganizationContact;
use App\Domain\Organization\OrganizationName;
use App\Domain\Organization\OrganizationRequest;
use App\Domain\Organization\Registration;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

/**
 * US-062-ALT (it. 43k, V10): the requests for an alta, from the Home to the
 * Super Administrador. The same rules as the alta (US-001, R-LEG-06) for the
 * name and the registration, and a valid contact email. No email goes to
 * whoever asks until the Super Administrador decides: the form must not send
 * mail to third parties.
 *
 * It. 46b: the request brings the PDF of its resolution or registration
 * certificate (RegistrationDocuments), and each decision keeps in the audit
 * log what the RUES said when the Super Administrador looked (RuesLookup).
 */
class OrganizationRequests
{
    /** @return array<string, string> what to correct, by field — all of it, not only the first; empty when it can be sent */
    public function problems(?string $name, ?string $email, ?string $number, ?string $authority): array
    {
        $problems = [];
        try {
            OrganizationName::fromString((string) $name);
        } catch (OrganizationValidationException $e) {
            $problems['name'] = $e->getMessage();
        }
        try {
            if (OrganizationContact::from($email, null)->email === null) {
                throw ContactRejected::email();
            }
        } catch (ContactRejected $e) {
            $problems['contact_email'] = $e->getMessage();
        }
        try {
            Registration::from($number, $authority) ?? throw OrganizationValidationException::incompleteRegistration();
        } catch (OrganizationValidationException $e) {
            $problems[filled($number) ? 'registration_authority' : 'registration_number'] = $e->getMessage();
        }

        return $problems;
    }

    /** Call after problems() came back empty, with the PDF RegistrationDocuments accepted. */
    public function submit(string $name, string $email, string $number, string $authority, UploadedFile $document): OrganizationRequest
    {
        $registration = Registration::from($number, $authority);

        $request = OrganizationRequest::create([
            'name' => OrganizationName::fromString($name)->value,
            'contact_email' => OrganizationContact::from($email, null)->email,
            'registration_number' => $registration->number,
            'registration_authority' => $registration->authority,
            'data_authorized_at' => now(),
            'data_policy_version' => DataPolicy::VERSION,
        ]);
        RegistrationDocuments::keepForRequest($request, $document);

        return $request;
    }

    /** @return list<array<string, mixed>> the pending ones, oldest first */
    public function pending(): array
    {
        return OrganizationRequest::query()->where('status', 'pending')->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (OrganizationRequest $request) => [
                ...$this->prefill($request),
                'received_at' => $request->created_at->toIso8601String(),
                'has_document' => $request->document_path !== null,
            ])->all();
    }

    public static function pendingCount(): int
    {
        return OrganizationRequest::query()->where('status', 'pending')->count();
    }

    /** @return array{id: int, name: string, contact_email: string, registration_number: string, registration_authority: string} */
    public function prefill(OrganizationRequest $request): array
    {
        // It. 46c (US-064-SEC): su identificador público, no su número.
        return ['id' => $request->public_id, ...$request->only(['name', 'contact_email', 'registration_number', 'registration_authority'])];
    }

    /** @return array<string, mixed> what the RUES says of the veeduría of the request (it. 46b) */
    public function rues(int $id): array
    {
        $request = OrganizationRequest::query()->findOrFail($id);

        return (new RuesLookup)->of(null, $request->registration_number, $request->registration_authority);
    }

    public function reject(int $id, string $reason, SuperAdmin $actor): OrganizationRequest
    {
        $request = $this->undecided($id);
        $request->update(['status' => 'rejected', 'rejection_reason' => $reason, 'decided_at' => now()]);
        Notification::route('mail', $request->contact_email)->notify(new OrganizationRequestRejected($request->name, $reason));
        $this->audit('organization_request.rejected', null, $actor, $request, ['reason' => $reason, 'rues' => $this->seen($request)]);

        return $request;
    }

    /** Registering the organization from the request (US-001) approves it. */
    public function approve(int $id, Tenant $tenant, SuperAdmin $actor): void
    {
        $request = $this->undecided($id);
        $request->update(['status' => 'approved', 'decided_at' => now(), 'tenant_id' => $tenant->id]);
        RegistrationDocuments::handOverTo($request, $tenant);
        $this->audit('organization_request.approved', $tenant->id, $actor, $request, ['tenant_id' => $tenant->id, 'rues' => $this->seen($request)]);
    }

    /** @return array<string, mixed>|null what the Super Administrador saw of the RUES before deciding */
    private function seen(OrganizationRequest $request): ?array
    {
        return RuesLookup::seen(null, $request->registration_number, $request->registration_authority);
    }

    private function undecided(int $id): OrganizationRequest
    {
        $request = OrganizationRequest::query()->findOrFail($id);
        if ($request->status !== 'pending') {
            throw new DomainException('Esta solicitud ya fue decidida.');
        }

        return $request;
    }

    /** @param  array<string, mixed>  $after */
    private function audit(string $action, ?string $organizationId, SuperAdmin $actor, OrganizationRequest $request, array $after): void
    {
        AuditLog::record(
            action: $action,
            organizationId: $organizationId,
            actorType: 'super_admin',
            actorId: (string) $actor->getKey(),
            actorName: $actor->name,
            before: ['request_id' => $request->id, 'name' => $request->name, 'contact_email' => $request->contact_email],
            after: $after,
        );
    }
}
