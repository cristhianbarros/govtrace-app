<?php

namespace App\Application\Organization;

use App\Domain\Audit\AuditLog;
use App\Domain\Organization\DisplayName;
use App\Domain\Organization\OrganizationContact;
use App\Domain\Organization\OrganizationLogo;
use App\Domain\Organization\User;
use Illuminate\Support\Facades\Storage;

/**
 * US-007: the Administrador de Organización changes the display name and,
 * optionally, the logo. Veedores see them on their next screen (both are
 * shared with every page). The NIT and the legal name aren't touched here:
 * only the Super Administrador changes them (US-011, R-TA-03). It. 43h (V13):
 * also its public contact; null keeps the one it has.
 */
class UpdateOrganizationProfile
{
    private const DISK = 'evidencias';

    public function handle(User $administrator, ?string $displayName, ?OrganizationLogo $logo, ?OrganizationContact $contact = null): void
    {
        $name = DisplayName::fromString($displayName);
        $tenant = tenant();
        $before = [
            'display_name' => $tenant->display_name,
            'logo' => $tenant->logo_path !== null,
            ...($contact ? ['contact' => ['email' => $tenant->contact_email, 'phone' => $tenant->contact_phone]] : []),
        ];
        $previousLogo = $tenant->logo_path;

        $changes = ['display_name' => $name->value];
        if ($contact !== null) {
            $changes['contact_email'] = $contact->email;
            $changes['contact_phone'] = $contact->phone;
        }
        if ($logo !== null) {
            // El hash en el nombre cambia la URL cuando cambia el logo: nadie ve uno viejo en caché.
            $path = sprintf('%s/profile/logo-%s.%s', $tenant->getTenantKey(), substr(hash('sha256', $logo->contents), 0, 16), $logo->extension);
            Storage::disk(self::DISK)->put($path, $logo->contents);
            $changes['logo_path'] = $path;
        }

        $tenant->update($changes);

        if ($logo !== null && $previousLogo !== null && $previousLogo !== $tenant->logo_path) {
            Storage::disk(self::DISK)->delete($previousLogo);
        }

        AuditLog::record(
            action: 'organization.profile_updated',
            organizationId: $tenant->getTenantKey(),
            actorType: 'organization_admin',
            actorId: (string) $administrator->id,
            actorName: $administrator->name,
            before: $before,
            after: [
                'display_name' => $tenant->display_name,
                'logo' => $tenant->logo_path !== null,
                ...($contact ? ['contact' => $contact->toArray()] : []),
            ],
        );
    }
}
