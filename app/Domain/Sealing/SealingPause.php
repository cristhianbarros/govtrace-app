<?php

namespace App\Domain\Sealing;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * US-020b: sin XLM en la cuenta patrocinadora, el sellado se pausa y se
 * reanuda solo cuando vuelve a tener saldo. Ningún reporte se pierde:
 * esperan "En Cola". Central: hay una sola patrocinadora para todas las
 * organizaciones. Una fila por pausa, así queda el historial.
 */
class SealingPause extends Model
{
    use CentralConnection;

    public $timestamps = false;

    protected $fillable = ['reason', 'paused_at', 'resumed_at'];

    protected $casts = [
        'paused_at' => 'datetime',
        'resumed_at' => 'datetime',
    ];

    public static function isActive(): bool
    {
        return self::query()->whereNull('resumed_at')->exists();
    }

    /** @return bool true si esta llamada abrió la pausa: hay que avisar una sola vez. */
    public static function start(string $reason): bool
    {
        if (self::isActive()) {
            return false;
        }

        self::create(['reason' => $reason, 'paused_at' => now()]);

        return true;
    }

    public static function end(): void
    {
        self::query()->whereNull('resumed_at')->update(['resumed_at' => now()]);
    }
}
