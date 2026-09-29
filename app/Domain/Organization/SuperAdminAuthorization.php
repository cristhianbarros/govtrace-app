<?php

namespace App\Domain\Organization;

use App\Domain\Organization\Exceptions\SuperAdminAuthorizationRejected;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * US-042-SEC (R-SA-02): el Administrador autoriza al Super Administrador a
 * crear reportes en nombre de su organización. Una por vez, vence a los 30
 * días y se puede revocar antes. Vive en la base de la organización, y
 * cada una se conserva.
 */
class SuperAdminAuthorization extends Model
{
    public const VALIDITY_DAYS = 30;

    protected $fillable = ['granted_by', 'granted_at', 'expires_at', 'revoked_by', 'revoked_at'];

    protected $casts = [
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public static function inForce(): ?self
    {
        return self::query()->whereNull('revoked_at')->where('expires_at', '>', now())->latest('granted_at')->first();
    }

    public static function grant(User $administrator): self
    {
        return DB::transaction(function () use ($administrator) {
            // Dos clics a la vez no dejan dos vigentes.
            DB::statement('LOCK TABLE super_admin_authorizations IN SHARE ROW EXCLUSIVE MODE');

            if ($current = self::inForce()) {
                throw SuperAdminAuthorizationRejected::alreadyInForce($current->expires_at);
            }

            $now = now()->startOfSecond();

            return self::query()->create([
                'granted_by' => $administrator->id,
                'granted_at' => $now,
                'expires_at' => $now->copy()->addDays(self::VALIDITY_DAYS),
            ]);
        });
    }

    public function revoke(User $administrator): void
    {
        $this->update(['revoked_by' => $administrator->id, 'revoked_at' => now()]);
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
