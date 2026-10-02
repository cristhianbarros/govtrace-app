<?php

namespace App\Application\Platform;

use App\Application\Privacy\DataPolicy;
use App\Application\Sealing\SuperAdminAlerts;
use App\Domain\Audit\AuditLog;
use App\Domain\Configuration\Parameters;
use App\Domain\Organization\Exceptions\InvitationRejected;
use App\Domain\Organization\InvitationToken;
use App\Domain\Organization\Notifications\WelcomeNotification;
use App\Domain\Platform\Notifications\OnlyOneSuperAdministrator;
use App\Models\User as SuperAdmin;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * It. 46a (US-063-USR): varios Super Administradores, y nunca ninguno. Uno
 * invita a otro (la misma invitación por correo de los administradores, US-030),
 * lo desactiva si se fue y lo reactiva si vuelve. La plataforma nunca queda
 * sin uno activo: nadie se desactiva a sí mismo, y desactivar bloquea las
 * cuentas activas, así que de dos que se desactivan el uno al otro al mismo
 * tiempo solo una desactivación se cumple. Una invitación pendiente no
 * cuenta. Al quedar uno solo, llega una alerta. `make admin` sigue siendo la
 * vía para recuperar el acceso. Todo va al log de auditoría (R-AUD-04).
 */
class SuperAdministrators
{
    public const NOT_YOURSELF = 'No puede desactivar su propia cuenta. Pídale a otro Super Administrador que lo haga.';

    public const ONLY_ACTIVE = 'No se puede desactivar al único Super Administrador activo. Invite a otro y espere a que active su cuenta.';

    public const EMAIL_TAKEN = 'El correo electrónico ya se encuentra registrado en el sistema.';

    private const LABELS = [
        'active' => 'Activo',
        'pending' => 'Invitación pendiente',
        'expired' => 'Invitación vencida',
        'inactive' => 'Inactivo',
    ];

    /** @return list<array{id: int, name: string, email: string, status: string, label: string, is_me: bool}> */
    public function list(SuperAdmin $viewer): array
    {
        return SuperAdmin::query()->orderBy('id')->get()->map(function (SuperAdmin $superAdmin) use ($viewer) {
            $status = match (true) {
                ! $superAdmin->is_active => 'inactive',
                $superAdmin->invitation_token_hash === null => 'active',
                $superAdmin->invitation_expires_at?->isPast() ?? false => 'expired',
                default => 'pending',
            };

            return [
                'id' => $superAdmin->id,
                'name' => $superAdmin->name,
                'email' => $superAdmin->email,
                'status' => $status,
                'label' => self::LABELS[$status],
                'is_me' => $superAdmin->is($viewer),
            ];
        })->all();
    }

    public static function activeCount(): int
    {
        return SuperAdmin::query()->active()->count();
    }

    /** @return int the hours the invitation is valid */
    public function invite(SuperAdmin $actor, string $name, string $email): int
    {
        $email = Str::lower(trim($email));
        if (SuperAdmin::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => self::EMAIL_TAKEN]);
        }

        $invited = new SuperAdmin;
        $invited->forceFill(['name' => trim($name), 'email' => $email, 'password' => null, 'is_active' => true])->save();
        $hours = $this->issueLink($invited);

        $this->audit('super_admin.invited', $actor, null, $this->invitation($invited));

        return $hours;
    }

    /** @return int the hours the new link is valid */
    public function resend(SuperAdmin $actor, int $id): int
    {
        $invited = $this->invited($id);
        $before = $this->invitation($invited);
        $hours = $this->issueLink($invited);
        $this->audit('super_admin.invitation_resent', $actor, $before, $this->invitation($invited));

        return $hours;
    }

    public function revoke(SuperAdmin $actor, int $id): string
    {
        $invited = $this->invited($id);
        $before = $this->invitation($invited);
        $invited->delete();
        $this->audit('super_admin.invitation_revoked', $actor, $before, null);

        return $before['email'];
    }

    /**
     * The active accounts are locked first: a second deactivation waits, and
     * then sees the first one done. So of two Super Administradores who
     * deactivate each other at the same time, only one deactivation holds.
     */
    public function deactivate(SuperAdmin $actor, int $id): void
    {
        if ($actor->getKey() === $id) {
            throw new DomainException(self::NOT_YOURSELF);
        }

        $leftAlone = DB::connection($actor->getConnectionName())->transaction(function () use ($actor, $id) {
            $active = SuperAdmin::query()->active()->orderBy('id')->lockForUpdate()->get();
            $target = $active->firstWhere('id', $id)
                ?? throw (new ModelNotFoundException)->setModel(SuperAdmin::class, [$id]);

            $othersActive = $active->reject(fn (SuperAdmin $superAdmin) => $superAdmin->is($target));
            if ($othersActive->isEmpty()) {
                throw new DomainException(self::ONLY_ACTIVE);
            }

            $this->changeAccess('super_admin.deactivated', $actor, $target, false);

            return $othersActive->count() === 1;
        });

        if ($leftAlone) {
            SuperAdminAlerts::send(new OnlyOneSuperAdministrator);
        }
    }

    public function reactivate(SuperAdmin $actor, int $id): void
    {
        $target = SuperAdmin::query()->whereKey($id)->where('is_active', false)->whereNull('invitation_token_hash')->firstOrFail();

        $this->changeAccess('super_admin.reactivated', $actor, $target, true);
    }

    /** The link is the one in the email and its hours have not passed. */
    public function isValidInvitation(SuperAdmin $invited, string $plainToken): bool
    {
        if (! $invited->invitation_token_hash || ! $invited->invitation_expires_at) {
            return false;
        }

        return hash_equals($invited->invitation_token_hash, InvitationToken::hashOf($plainToken))
            && $invited->invitation_expires_at->isFuture();
    }

    /** US-030 for a Super Administrador: the password, the name and the authorization of the data (US-058-LEG). */
    public function accept(SuperAdmin $invited, string $plainToken, string $name, string $password): void
    {
        if (! $this->isValidInvitation($invited, $plainToken)) {
            throw InvitationRejected::expiredOrInvalid();
        }

        DB::connection($invited->getConnectionName())->transaction(function () use ($invited, $name, $password) {
            $invited->forceFill([
                'name' => trim($name),
                'password' => $password, // the "hashed" cast takes care of it
                'invitation_token_hash' => null,
                'invitation_expires_at' => null,
                'is_active' => true,
            ])->save();

            (new DataPolicy)->authorizeSuperAdministrator($invited);
            $this->audit('super_admin.activated', $invited, null, ['user_id' => $invited->id, 'email' => $invited->email]);
        });
    }

    private function issueLink(SuperAdmin $invited): int
    {
        $token = InvitationToken::generate();
        $hours = (int) (Parameters::current('invitation_validity_hours') ?? 48);
        $invited->forceFill(['invitation_token_hash' => $token->hash, 'invitation_expires_at' => now()->addHours($hours)])->save();
        $invited->notify(new WelcomeNotification(url("/set-password/{$invited->id}?token={$token->plain}"), $hours));

        return $hours;
    }

    private function changeAccess(string $action, SuperAdmin $actor, SuperAdmin $target, bool $active): void
    {
        $before = ['user_id' => $target->id, 'email' => $target->email, 'is_active' => (bool) $target->is_active];
        $target->forceFill(['is_active' => $active])->save();
        $this->audit($action, $actor, $before, [...$before, 'is_active' => $active]);
    }

    /** A Super Administrador whose invitation is unanswered (pending or expired); anything else is not found. */
    private function invited(int $id): SuperAdmin
    {
        return SuperAdmin::query()->whereKey($id)->whereNotNull('invitation_token_hash')->firstOrFail();
    }

    /** @return array{user_id: int, email: string, invitation_expires_at: string|null} */
    private function invitation(SuperAdmin $invited): array
    {
        return ['user_id' => $invited->id, 'email' => $invited->email, 'invitation_expires_at' => $invited->invitation_expires_at?->toIso8601String()];
    }

    private function audit(string $action, SuperAdmin $actor, ?array $before, ?array $after): void
    {
        AuditLog::record(action: $action, actorType: 'super_admin', actorId: (string) $actor->id, actorName: $actor->name, before: $before, after: $after);
    }
}
