<?php

namespace App\Application\Sealing;

use App\Application\Sealing\Exceptions\RootAlreadySealed;
use App\Application\Sealing\Exceptions\SealingNetworkError;
use App\Application\Sealing\Exceptions\SponsorOutOfFunds;

/**
 * El puerto hacia la red donde se sella (US-020b). La implementación real
 * es App\Infrastructure\Stellar\StellarSealingNetwork; los tests usan un
 * doble en memoria (Tests\Support\FakeSealingNetwork).
 */
interface SealingNetwork
{
    /**
     * Envía seal(obra, raíz) al contrato: lo firma la cuenta selladora y la
     * comisión la paga la patrocinadora con un fee bump (D5). Devuelve el
     * hash de la transacción, todavía sin confirmar.
     *
     * @throws RootAlreadySealed la red ya tiene esa raíz ("Hash ya registrado")
     * @throws SponsorOutOfFunds la patrocinadora no tiene XLM para la comisión
     * @throws SealingNetworkError cualquier otro rechazo de la red
     */
    public function submitSeal(string $worksiteReference, string $merkleRoot): string;

    /**
     * null mientras la red no cierre un ledger con la transacción.
     *
     * @throws SealingNetworkError si la red la incluyó pero falló
     */
    public function transactionStatus(string $txHash): ?NetworkSeal;

    /**
     * El sello que la red ya tiene para la raíz, o null. Lo lee de las
     * entradas del ledger: sirve igual si el sello está archivado por su
     * vigencia (TTL), y no necesita ninguna cuenta (it. 23). Con la
     * transacción que lo selló, si la red todavía la recuerda.
     */
    public function findSeal(string $merkleRoot): ?NetworkSeal;

    /** La dirección del contrato de sellado (C…): va en el recibo y en la prueba de inclusión. */
    public function contractId(): string;

    public function sponsorCanPay(): bool;

    public function sponsorAddress(): string;
}
