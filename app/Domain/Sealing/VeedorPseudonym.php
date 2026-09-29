<?php

namespace App\Domain\Sealing;

use App\Domain\Organization\User;
use Illuminate\Database\Eloquent\Model;

/**
 * D7 / R-PRIV-03: el seudónimo del veedor que va en el JSON sellado. Es un
 * HMAC de «organización:veedor» con una llave del servidor: estable para el
 * mismo veedor, imposible de invertir sin la llave. Esta tabla es la única
 * vuelta del seudónimo al veedor, y se conserva 5 años desde su último
 * reporte (R-MNT-03, PurgeVeedorPseudonyms).
 */
class VeedorPseudonym extends Model
{
    protected $fillable = ['user_id', 'pseudonym'];

    /** The pseudonym that gets sealed, keeping its way back to the veedor for 5 years (R-MNT-03). */
    public static function of(User $veedor): string
    {
        $pseudonym = self::compute($veedor);

        self::query()->createOrFirst(['user_id' => $veedor->id], ['pseudonym' => $pseudonym]);

        return $pseudonym;
    }

    /**
     * The same pseudonym, without keeping the way back: for the exports
     * (US-050-RPT, US-052-RPT), so they never bring back a purged one.
     */
    public static function compute(User $veedor): string
    {
        return hash_hmac('sha256', tenant()->getTenantKey().':'.$veedor->id, self::key());
    }

    /** SEALING_PSEUDONYM_KEY; si no está, una derivada de APP_KEY (nunca APP_KEY a secas). */
    private static function key(): string
    {
        return (string) (config('sealing.pseudonym_key') ?: hash_hmac('sha256', 'govtrace-veedor-pseudonym', (string) config('app.key')));
    }
}
