<?php

use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\Exceptions\SealingNetworkBusy;
use App\Infrastructure\Stellar\SealerTurn;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/*
 * Iteración 39 — el turno de la cuenta selladora. Stellar admite una sola
 * transacción pendiente por cuenta (probado contra la red local): mientras la
 * última de la selladora no entra en un ledger, otra se rechaza, con
 * TRY_AGAIN_LATER o, si repite su número de secuencia, con txINSUFFICIENT_FEE,
 * porque la red la lee como un reemplazo que tendría que pagar 10 veces más.
 * Así que la selladora sella por turnos, y el turno vive en la base central:
 * uno solo para todas las organizaciones y todos los workers. El candado de
 * antes estaba en la caché, que dentro de una organización lleva su prefijo:
 * cada organización tenía el suyo.
 *
 * Sin RefreshDatabase: el turno se prueba también desde otra conexión, que
 * no vería lo que una transacción de prueba no confirmó; y registrar una
 * organización ejecuta CREATE DATABASE.
 */

const TURN_SEALER = 'GB4FFPG7U75E6GL26DAJK2FWC7M5ARCVOJGRXP6C357QRUGTGJ7TGGMS';

beforeEach(function () {
    $this->artisan('migrate');
    $this->freezeSecond();
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('sealer_turns')->delete();
});

/** The turn of TURN_SEALER, taken by the transaction $txHash until $until (by default, its 4 minutes and the grace after them). */
function takeTurn(string $txHash, ?DateTimeInterface $until = null): void
{
    SealerTurn::exclusively(TURN_SEALER, fn (SealerTurn $turn) => $turn->takeFor($txHash, $until ?? now()->addSeconds(270)));
}

/** @return array{tx_hash: string|null, taken: bool} */
function turnNow(): array
{
    return SealerTurn::exclusively(TURN_SEALER, fn (SealerTurn $turn) => ['tx_hash' => $turn->tx_hash, 'taken' => $turn->isTaken()]);
}

/** Waits for the turn, if taken; $stillPending answers for the network whether its transaction is still waiting for a ledger. */
function waitForTurn(Closure $stillPending): void
{
    SealerTurn::exclusively(TURN_SEALER, fn (SealerTurn $turn) => $turn->waitIfTaken($stillPending));
}

/** Another process, with its own connection to the central database. */
function otherProcess(): Connection
{
    config(['database.connections.pgsql_other_process' => config('database.connections.pgsql')]);

    return DB::connection('pgsql_other_process');
}

it('leaves the turn free for a sealer that never sealed', function () {
    expect(turnNow())->toBe(['tx_hash' => null, 'taken' => false]);
});

it('keeps one turn for every organization: a transaction pending for one makes the others wait', function () {
    $santaMarta = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    $cienaga = (new RegisterOrganization)->handle('890000062-6', 'Veeduría Ciénaga', 'veeduria-cienaga');

    $santaMarta->run(fn () => takeTurn(str_repeat('a', 64)));

    expect($cienaga->run(fn () => turnNow()))->toBe(['tx_hash' => str_repeat('a', 64), 'taken' => true])
        ->and(turnNow()['taken'])->toBeTrue();
});

it('never waits on a lock: while another process is in its turn, the next one is told to come back in a few seconds', function () {
    turnNow(); // la fila existe
    $other = otherProcess();
    $other->beginTransaction();
    $other->table('sealer_turns')->where('account', TURN_SEALER)->lockForUpdate()->first();
    // Si esperara al candado, a los 5 s Postgres lo corta: el test no se cuelga.
    DB::connection('pgsql')->statement("set lock_timeout = '5s'");
    $started = microtime(true);

    try {
        SealerTurn::exclusively(TURN_SEALER, fn () => 'mi turno');
        $this->fail('Tomó el turno que otro proceso tenía.');
    } catch (SealingNetworkBusy $busy) {
        expect(microtime(true) - $started)->toBeLessThan(1.0)
            ->and($busy->retryAfterSeconds)->toBe(SealerTurn::RETRY_SECONDS);
    } finally {
        $other->rollBack();
        DB::connection('pgsql')->statement('set lock_timeout = 0');
    }
});

it('keeps what a turn wrote, also when the turn ends in an exception', function () {
    expect(fn () => SealerTurn::exclusively(TURN_SEALER, function (SealerTurn $turn) {
        $turn->takeFor(str_repeat('b', 64), now()->addSeconds(270));

        throw new RuntimeException('la respuesta del envío nunca llegó');
    }))->toThrow(RuntimeException::class, 'la respuesta del envío nunca llegó');

    expect(turnNow())->toBe(['tx_hash' => str_repeat('b', 64), 'taken' => true]);
});

it('keeps the turn while its transaction is pending, and asks the network at most once every 2 seconds', function () {
    takeTurn(str_repeat('c', 64));
    $asked = [];
    $stillPending = function (string $txHash) use (&$asked) {
        $asked[] = $txHash;

        return true;
    };

    expect(fn () => waitForTurn($stillPending))->toThrow(SealingNetworkBusy::class)
        // Al instante, otro sello: no vuelve a preguntar.
        ->and(fn () => waitForTurn($stillPending))->toThrow(SealingNetworkBusy::class);
    $this->travel(SealerTurn::CHECK_EVERY_SECONDS)->seconds();

    expect(fn () => waitForTurn($stillPending))->toThrow(SealingNetworkBusy::class)
        ->and($asked)->toBe([str_repeat('c', 64), str_repeat('c', 64)]);
});

it('frees the turn once its transaction is no longer pending: it entered a ledger, or the network rejected it', function () {
    takeTurn(str_repeat('d', 64));

    waitForTurn(fn () => false);

    expect(turnNow())->toBe(['tx_hash' => null, 'taken' => false]);
});

it('frees the turn of a transaction that never entered once its time bounds are over, without asking the network', function () {
    takeTurn(str_repeat('e', 64), now()->addSeconds(270));
    $this->travel(271)->seconds();

    waitForTurn(fn () => throw new LogicException('No hacía falta preguntar: ya no puede entrar.'));

    expect(turnNow()['taken'])->toBeFalse();
});

it('frees the turn only for the transaction that holds it', function () {
    takeTurn(str_repeat('f', 64));

    SealerTurn::release(TURN_SEALER, str_repeat('0', 64));
    expect(turnNow())->toBe(['tx_hash' => str_repeat('f', 64), 'taken' => true]);

    SealerTurn::release(TURN_SEALER, str_repeat('f', 64));
    expect(turnNow())->toBe(['tx_hash' => null, 'taken' => false]);
});

it('leaves the sealer alone for a ledger when the network says it has another transaction pending, without asking about it', function () {
    takeTurn(str_repeat('9', 64));

    SealerTurn::holdAfterRefusal(TURN_SEALER, str_repeat('9', 64));
    $unknown = fn () => throw new LogicException('No hay a quién preguntar: la pendiente no es nuestra.');

    expect(fn () => waitForTurn($unknown))->toThrow(SealingNetworkBusy::class)
        ->and(turnNow()['tx_hash'])->toBeNull();
    $this->travel(SealerTurn::HOLD_SECONDS)->seconds();

    waitForTurn($unknown);
    expect(turnNow()['taken'])->toBeFalse();
});
