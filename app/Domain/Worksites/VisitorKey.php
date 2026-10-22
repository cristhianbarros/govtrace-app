<?php

namespace App\Domain\Worksites;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * It. 69 (US-071, R-PRIV-07): the key of a day, in the central database — 32
 * random bytes, the same for every organization and worker. It does not come
 * from the date or from APP_KEY: once deleted, the next day, nobody can make
 * it again, so the fingerprints of that day cannot be traced back to anyone.
 */
final class VisitorKey extends Model
{
    use CentralConnection;

    protected $primaryKey = 'day';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['day', 'key'];

    /** The key of $day (Y-m-d), made the first time it is asked for. */
    public static function of(string $day): string
    {
        $key = self::query()->whereKey($day)->value('key');
        if ($key === null) {
            self::query()->insertOrIgnore(['day' => $day, 'key' => bin2hex(random_bytes(32))]);
            // Otra visita pudo hacerla primero: vale la que quedó.
            $key = self::query()->whereKey($day)->value('key');
        }

        return $key;
    }
}
