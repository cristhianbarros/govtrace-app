<?php

namespace App\Infrastructure\Stellar;

use App\Application\Sealing\Exceptions\SealingNetworkBusy;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * El turno de la cuenta selladora (it. 39). Stellar admite una sola
 * transacción pendiente por cuenta, probado contra la red: mientras la última
 * de la selladora no entra en un ledger, otra se rechaza —con TRY_AGAIN_LATER,
 * o con txINSUFFICIENT_FEE si repite su número de secuencia, porque la red la
 * lee como un reemplazo que tendría que pagar 10 veces más—. Así que la
 * selladora sella por turnos.
 *
 * El turno vive en la base central, una fila por cuenta: el mismo para todas
 * las organizaciones y todos los workers. El candado de antes estaba en la
 * caché, que dentro de una organización lleva su prefijo: cada una tenía el
 * suyo. Una transacción toma el turno desde que existe, antes de enviarla
 * (un envío sin respuesta pudo haber llegado igual), y lo suelta cuando entra
 * en un ledger, cuando la red la rechaza o cuando vencen sus 4 minutos:
 * después, ya no puede entrar.
 */
final class SealerTurn extends Model
{
    use CentralConnection;

    /** A transaction that never entered is spent when its time bounds end; this much later, not even a network clock a little behind ours lets it in. */
    public const EXPIRY_GRACE_SECONDS = 30;

    /** How often the network is asked whether the transaction holding the turn entered: about a ledger. */
    public const CHECK_EVERY_SECONDS = 2;

    /** How long the sealer is left alone after the network said it has another transaction pending, one this turn does not know of. */
    public const HOLD_SECONDS = 5;

    /** How soon a seal that found the turn taken comes back. */
    public const RETRY_SECONDS = 3;

    /** Postgres: the row is locked by another process (NOWAIT does not wait), or a lock_timeout ran out. */
    private const LOCK_NOT_AVAILABLE = '55P03';

    protected $primaryKey = 'account';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['account', 'tx_hash', 'taken_until', 'checked_at'];

    protected $casts = [
        'taken_until' => 'datetime',
        'checked_at' => 'datetime',
    ];

    /**
     * $turn runs holding the turn of $account, for this process alone
     * (SELECT … FOR UPDATE NOWAIT), and what it wrote stays, also when it ends
     * in an exception. If another process is in the middle of its turn, the
     * seal is told to come back: nobody waits on a lock.
     *
     * @template T
     *
     * @param  Closure(self): T  $turn
     * @return T
     *
     * @throws SealingNetworkBusy
     */
    public static function exclusively(string $account, Closure $turn): mixed
    {
        $connection = (new self)->getConnection();
        self::query()->insertOrIgnore(['account' => $account, 'created_at' => now(), 'updated_at' => now()]);

        $connection->beginTransaction();
        try {
            $locked = self::query()->whereKey($account)->lock('for update nowait')->firstOrFail();
        } catch (QueryException $e) {
            $connection->rollBack();

            if (($e->errorInfo[0] ?? $e->getCode()) === self::LOCK_NOT_AVAILABLE) {
                throw new SealingNetworkBusy(self::RETRY_SECONDS, 'Otro proceso está enviando un sello de la cuenta selladora: este espera su turno.');
            }

            throw $e;
        }

        try {
            return $turn($locked);
        } finally {
            $connection->commit();
        }
    }

    public function isTaken(): bool
    {
        return $this->taken_until?->isFuture() ?? false;
    }

    /**
     * If the turn is taken, asks the network whether the transaction that
     * holds it is still pending —at most once every CHECK_EVERY_SECONDS— and
     * frees the turn as soon as it is not.
     *
     * @param  Closure(string): bool  $stillPending  whether the transaction with that hash is still waiting for a ledger
     *
     * @throws SealingNetworkBusy while it is
     */
    public function waitIfTaken(Closure $stillPending): void
    {
        if (! $this->isTaken()) {
            return;
        }

        $checkedRecently = $this->checked_at?->copy()->addSeconds(self::CHECK_EVERY_SECONDS)->isFuture() ?? false;
        if ($this->tx_hash === null || $checkedRecently) {
            throw $this->busy();
        }

        $this->update(['checked_at' => now()]);
        if ($stillPending($this->tx_hash)) {
            throw $this->busy();
        }

        $this->update(['tx_hash' => null, 'taken_until' => null, 'checked_at' => null]);
    }

    /** The transaction $txHash, built and signed, takes the turn until $until: from before it is sent. */
    public function takeFor(string $txHash, DateTimeInterface $until): void
    {
        $this->update(['tx_hash' => $txHash, 'taken_until' => $until, 'checked_at' => null]);
    }

    /** $txHash entered a ledger, or the network rejected it: its sequence number is spent or free, and so is the turn. */
    public static function release(string $account, string $txHash): void
    {
        self::query()->whereKey($account)->where('tx_hash', $txHash)
            ->update(['tx_hash' => null, 'taken_until' => null, 'checked_at' => null, 'updated_at' => now()]);
    }

    /**
     * The network did not take $txHash because the sealer has another
     * transaction pending, one this turn does not know of: sent from elsewhere,
     * or read from an RPC one ledger behind. The sealer is left alone for a
     * ledger or so.
     */
    public static function holdAfterRefusal(string $account, string $txHash): void
    {
        self::query()->whereKey($account)->where('tx_hash', $txHash)
            ->update(['tx_hash' => null, 'taken_until' => now()->addSeconds(self::HOLD_SECONDS), 'checked_at' => null, 'updated_at' => now()]);
    }

    private function busy(): SealingNetworkBusy
    {
        return new SealingNetworkBusy(self::RETRY_SECONDS, $this->tx_hash === null
            ? 'La cuenta selladora tiene otra transacción pendiente: este sello espera su turno.'
            : "La cuenta selladora tiene pendiente la transacción {$this->tx_hash}: este sello espera su turno.");
    }
}
