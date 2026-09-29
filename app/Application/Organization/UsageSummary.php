<?php

namespace App\Application\Organization;

use App\Domain\Organization\Roles;
use App\Domain\Organization\User;
use App\Domain\Reports\EditorialStatus;
use App\Domain\Reports\Report;
use App\Infrastructure\Tenancy\Tenant;

/**
 * US-053-RPT: el uso de cada organización, para el Super Administrador —
 * veedores activos (ni desactivados ni con la invitación pendiente),
 * evidencias recibidas y por estado editorial, y su última actividad
 * (US-054-RPT). En el mismo orden que el listado de organizaciones.
 */
final class UsageSummary
{
    public function __construct(private readonly OrganizationActivity $activity) {}

    /** @return list<array<string, mixed>> */
    public function rows(): array
    {
        return Tenant::query()->orderBy('name')->get()->map(function (Tenant $tenant) {
            [$observers, $byStatus] = $tenant->run(fn () => [
                User::role(Roles::Observer->value, 'tenant')->where('is_active', true)->whereNull('invitation_token_hash')->count(),
                Report::query()->selectRaw('editorial_status, count(*) as total')->groupBy('editorial_status')->pluck('total', 'editorial_status')->all(),
            ]);

            return [
                'organization' => $tenant->displayName(),
                'status' => $tenant->statusLabel(),
                'active_observers' => $observers,
                'received' => (int) array_sum($byStatus),
                'published' => (int) ($byStatus[EditorialStatus::Published->value] ?? 0),
                'rejected' => (int) ($byStatus[EditorialStatus::Rejected->value] ?? 0),
                'withdrawn' => (int) ($byStatus[EditorialStatus::Withdrawn->value] ?? 0),
                'last_activity_at' => $this->activity->lastOf($tenant)?->timezone('America/Bogota')->toIso8601String(),
            ];
        })->all();
    }
}
