<?php

namespace App\Domain\Sealing;

use App\Domain\Organization\User;
use Illuminate\Database\Eloquent\Model;

/**
 * D7 / R-PRIV-03: el seudónimo del veedor que va en el JSON sellado. Es un
 * HMAC de «organización:veedor» con una llave del servidor: estable para el
 * mismo veedor, imposible de invertir sin la llave. Esta tabla es la única
 * vuelta del seudónimo al veedor, y se conserva 5 años (R-MNT-03).
 */
class VeedorPseudonym extends Model
{
    protected $fillable = ['user_id', 'pseudonym'];

    public static function of(User $veedor): string
    {
        $pseudonym = hash_hmac('sha256', tenant()->getTenantKey().':'.$veedor->id, self::key());

        self::query()->createOrFirst(['user_id' => $veedor->id], ['pseudonym' => $pseudonym]);

        return $pseudonym;
    }

    /** SEALING_PSEUDONYM_KEY; si no está, una derivada de APP_KEY (nunca APP_KEY a secas). */
    private static function key(): string
    {
        return (string) (config('sealing.pseudonym_key') ?: hash_hmac('sha256', 'govtrace-veedor-pseudonym', (string) config('app.key')));
    }
}
