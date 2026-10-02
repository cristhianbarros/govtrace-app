<?php

namespace App\Models;

use App\Domain\Auth\Notifications\ResetPasswordLink;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    // El Super Administrador vive en la base central. Sin esto, leerlo desde
    // el contexto de una organización (p. ej. para avisarle que la
    // patrocinadora se quedó sin XLM, it. 13) iría a la tabla "users" de esa
    // organización: sus veedores.
    use CentralConnection, HasFactory, Notifiable;

    /**
     * It. 46a (US-063-USR): a Super Administrador who can act — active, and
     * with the account activated (a pending invitation does not count). The
     * only ones who get the alerts, and the ones the platform never runs out of.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->whereNull('invitation_token_hash');
    }

    /** US-039-USR: the link goes to the global panel. */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordLink(url("/reset-password/{$token}").'?email='.urlencode($this->email), $token));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'invitation_expires_at' => 'datetime',
            'data_authorized_at' => 'datetime',
        ];
    }
}
