<?php

namespace App\Application\Organization;

use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Exceptions\SuperAdminAuthorizationRejected;
use App\Domain\Organization\SuperAdminAuthorization;
use App\Domain\Organization\User;
use Illuminate\Support\Facades\DB;

/**
 * US-042-SEC: el Administrador de Organización otorga o revoca la
 * autorización al Super Administrador para reportar en nombre de la
 * organización (R-SA-02). Cada una queda en el log de auditoría (R-AUD-04);
 * si el log falla, la autorización no cambia.
 */
class SuperAdminAuthorizations
{
    public function grant(User $administrator): SuperAdminAuthorization
    {
        return DB::transaction(function () use ($administrator) {
            $authorization = SuperAdminAuthorization::grant($administrator);

            $this->audit('super_admin.authorized', $administrator, [
                'authorization_id' => $authorization->id,
                'expires_at' => $authorization->expires_at->toIso8601String(),
            ]);

            return $authorization;
        });
    }

    public function revoke(User $administrator): void
    {
        DB::transaction(function () use ($administrator) {
            $authorization = SuperAdminAuthorization::inForce() ?? throw SuperAdminAuthorizationRejected::noneInForce();
            $authorization->revoke($administrator);

            $this->audit('super_admin.authorization_revoked', $administrator, ['authorization_id' => $authorization->id]);
        });
    }

    /** @param  array<string, mixed>  $after */
    private function audit(string $action, User $administrator, array $after): void
    {
        AuditLog::record(
            action: $action,
            organizationId: tenant()->getTenantKey(),
            actorType: 'organization_admin',
            actorId: (string) $administrator->id,
            actorName: $administrator->name,
            after: $after,
        );
    }
}
