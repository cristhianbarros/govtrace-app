<?php

namespace App\Application\Worksites;

use App\Domain\Audit\AuditLog;
use App\Domain\Geography\GeoPoint;
use App\Domain\Organization\User;
use App\Domain\Worksites\Worksite;
use Illuminate\Support\Facades\DB;

/**
 * US-035: el Administrador de Organización corrige la ubicación oficial de
 * una obra de SU organización, para que una ubicación errónea no bloquee
 * a sus veedores. Cada corrección queda en el log de auditoría (R-AUD-04):
 * quién, cuándo, las coordenadas anteriores y las nuevas.
 *
 * La ficha vive en la base de la organización, así que la misma obra en
 * otra organización ni se entera.
 */
class CorrectWorksiteLocation
{
    public function handle(User $administrator, Worksite $worksite, GeoPoint $newLocation): void
    {
        // El log vive en la base central; si falla, la ficha no cambia.
        DB::transaction(function () use ($administrator, $worksite, $newLocation) {
            $previous = $worksite->location();

            $worksite->relocateTo($newLocation);

            AuditLog::record(
                action: 'worksite.location_corrected',
                organizationId: tenant()->getTenantKey(),
                actorType: 'organization_admin',
                actorId: (string) $administrator->id,
                actorName: $administrator->name,
                before: ['worksite_id' => $worksite->id, 'latitude' => $previous?->latitude, 'longitude' => $previous?->longitude],
                after: ['worksite_id' => $worksite->id, 'latitude' => $newLocation->latitude, 'longitude' => $newLocation->longitude],
            );
        });
    }
}
