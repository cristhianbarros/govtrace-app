<?php

namespace App\Application\Organization;

use App\Domain\Audit\AuditLog;
use App\Domain\Configuration\Parameters;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Closure;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * It. 43a (V2 y V16 de docs/mapa-funcional.md): el Administrador de una
 * organización, desde el panel global. El Super Administrador ve quién la
 * administra y en qué va su invitación; la reenvía si no la respondieron,
 * la revoca si el correo estaba mal, y asigna uno si no hay ninguno (US-002:
 * "tras el alta, o en un paso consecutivo"). Reemplazar a un Administrador
 * activo, o sumar otro, es una decisión pendiente (V3), y aquí no se permite.
 * Cada cambio va al log de auditoría (R-AUD-04), como los del Administrador
 * con sus veedores (US-040-USR).
 */
class OrganizationAdministrators
{
    private const LABELS = [
        'active' => 'Activo',
        'pending' => 'Invitación pendiente',
        'expired' => 'Invitación vencida',
        'inactive' => 'Inactivo',
    ];

    /** @return list<array{id: int, name: string, email: string, status: string, label: string}> */
    public function of(Tenant $tenant): array
    {
        return $this->inside($tenant, fn () => User::role(Roles::Administrator->value, 'tenant')->orderBy('id')->get()
            ->map(function (User $administrator) {
                $status = match (true) {
                    ! $administrator->is_active => 'inactive',
                    $administrator->invitation_token_hash === null => 'active',
                    $administrator->invitation_expires_at?->isPast() ?? false => 'expired',
                    default => 'pending',
                };

                return ['id' => $administrator->id, 'name' => $administrator->name, 'email' => $administrator->email, 'status' => $status, 'label' => self::LABELS[$status]];
            })->all());
    }

    /**
     * Only when the organization has no Administrador at all — active, or
     * invited (V3 decides whether there can be more than one).
     *
     * @return int the hours the invitation is valid
     */
    public function assign(Tenant $tenant, string $name, string $email): int
    {
        $hasOne = $this->inside($tenant, fn () => User::role(Roles::Administrator->value, 'tenant')->exists());
        if ($hasOne) {
            throw new DomainException('La organización ya tiene un Administrador. Si su invitación quedó con un correo equivocado, revóquela primero.');
        }

        (new AssignInitialAdministrator)->handle($tenant, $name, $email);

        // La vigencia con la que AssignInitialAdministrator emitió el enlace (US-038-CFG).
        return (int) (Parameters::current('invitation_validity_hours') ?? 48);
    }

    /** @return int the hours the new link is valid */
    public function resend(Tenant $tenant, int $userId, SuperAdmin $actor): int
    {
        return $this->inside($tenant, function () use ($tenant, $userId, $actor) {
            $administrator = $this->invited($userId);
            $before = InvitationLink::audited($administrator);
            $hours = InvitationLink::issue($administrator);
            $this->audit('invitation.resent', $tenant, $actor, $before, InvitationLink::audited($administrator));

            return $hours;
        });
    }

    public function revoke(Tenant $tenant, int $userId, SuperAdmin $actor): string
    {
        return $this->inside($tenant, function () use ($tenant, $userId, $actor) {
            $administrator = $this->invited($userId);
            $before = InvitationLink::audited($administrator);
            $administrator->syncRoles([]);
            $administrator->delete();
            $this->audit('invitation.revoked', $tenant, $actor, $before, null);

            return $before['email'];
        });
    }

    /** An Administrador whose invitation is still unanswered (pending or expired); anything else is not found. */
    private function invited(int $userId): User
    {
        $administrator = User::role(Roles::Administrator->value, 'tenant')->whereKey($userId)->first();
        if ($administrator === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$userId]);
        }
        if ($administrator->invitation_token_hash === null) {
            throw OrganizationValidationException::noPendingInvitation();
        }

        return $administrator;
    }

    /** @param  array<string, mixed>|null  $after */
    private function audit(string $action, Tenant $tenant, SuperAdmin $actor, array $before, ?array $after): void
    {
        AuditLog::record(
            action: $action,
            organizationId: $tenant->id,
            actorType: 'super_admin',
            actorId: (string) $actor->getKey(),
            actorName: $actor->name,
            before: $before,
            after: $after,
        );
    }

    /**
     * Inside the organization, and back to the central context whatever
     * happens (like AssignInitialAdministrator: $tenant->run() does not
     * revert when the callback throws).
     */
    private function inside(Tenant $tenant, Closure $work): mixed
    {
        $original = tenant();
        tenancy()->initialize($tenant);
        try {
            return $work();
        } finally {
            $original ? tenancy()->initialize($original) : tenancy()->end();
        }
    }
}
