<?php

namespace App\Domain\Organization;

use App\Domain\Auth\Notifications\ResetPasswordLink;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * An Administrador de Organización or a Veedor de Campo of ONE
 * organization. Lives in that tenant's own database (specs/SPEC.md,
 * decisión de tenancy) — never in the central "users" table, which is
 * reserved for the Super Administrator.
 */
class User extends Model implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable, CanResetPassword, HasRoles, Notifiable;

    /**
     * Every role here uses this guard (config/auth.php "tenant" guard) —
     * spatie/permission needs it to match when checking $user->hasRole().
     */
    protected string $guard_name = 'tenant';

    protected $fillable = [
        'name', 'email', 'password', 'is_active',
        'invitation_token_hash', 'invitation_expires_at',
    ];

    protected $hidden = ['password', 'remember_token', 'invitation_token_hash'];

    /** US-039-USR: the link goes to this organization's own subdomain. */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $domain = tenant()->domains()->first()->domain;
        $url = "http://{$domain}/reset-password/{$token}?email=".urlencode($this->email);

        $this->notify(new ResetPasswordLink($url, $token));
    }

    /** US-005: how the Administrador sees each member of the team. */
    public function statusLabel(): string
    {
        if ($this->invitation_token_hash !== null) {
            return $this->invitation_expires_at?->isFuture() ? 'Invitación pendiente' : 'Invitación vencida';
        }

        return $this->is_active ? 'Activo' : 'Inactivo';
    }

    protected $casts = [
        'email_verified_at' => 'datetime',
        'invitation_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];
}
