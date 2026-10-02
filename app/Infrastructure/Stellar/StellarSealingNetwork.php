<?php

namespace App\Infrastructure\Stellar;

use App\Application\Sealing\ContractLifetime;
use App\Application\Sealing\Exceptions\RootAlreadySealed;
use App\Application\Sealing\Exceptions\SealingNetworkBusy;
use App\Application\Sealing\Exceptions\SealingNetworkError;
use App\Application\Sealing\Exceptions\SponsorOutOfFunds;
use App\Application\Sealing\NetworkSeal;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Sealing\SealingRetryPolicy;
use Carbon\CarbonImmutable;
use DateTime;
use Soneso\StellarSDK\Account;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\FeeBumpTransaction;
use Soneso\StellarSDK\FeeBumpTransactionBuilder;
use Soneso\StellarSDK\InvokeContractHostFunction;
use Soneso\StellarSDK\InvokeHostFunctionOperationBuilder;
use Soneso\StellarSDK\Network;
use Soneso\StellarSDK\Soroban\Responses\GetTransactionResponse;
use Soneso\StellarSDK\Soroban\Responses\SendTransactionResponse;
use Soneso\StellarSDK\Soroban\Responses\SimulateTransactionResponse;
use Soneso\StellarSDK\TimeBounds;
use Soneso\StellarSDK\Transaction;
use Soneso\StellarSDK\TransactionBuilder;
use Soneso\StellarSDK\Xdr\XdrContractDataDurability;
use Soneso\StellarSDK\Xdr\XdrLedgerKey;
use Soneso\StellarSDK\Xdr\XdrSCAddress;
use Soneso\StellarSDK\Xdr\XdrSCVal;

/**
 * El sellado en Stellar (US-020b, D5): invoca seal(obra, raíz) del contrato
 * de contracts/sealing. La transacción la firma la cuenta selladora y la
 * envuelve en un fee bump la cuenta patrocinadora, que es la única que paga.
 * El veedor no ve nada de esto (R-BLK-01).
 *
 * La selladora sella por turnos (it. 39): Stellar admite una sola
 * transacción pendiente por cuenta, así que un sello espera a que la anterior
 * entre en un ledger (SealerTurn).
 */
final class StellarSealingNetwork implements SealingNetwork
{
    private const STROOPS_PER_XLM = 10_000_000;

    /** Error(Contract, #1) = HashAlreadyRegistered, en contracts/sealing/src/lib.rs. */
    private const HASH_ALREADY_REGISTERED = 'Error(Contract, #1)';

    public function __construct(
        private readonly StellarRpc $rpc,
        private readonly string $networkPassphrase,
        private readonly string $contractId,
        private readonly string $sealerSecret,
        private readonly string $sponsorSecret,
        private readonly int $sponsorMinBalanceXlm,
    ) {}

    public function submitSeal(string $worksiteReference, string $merkleRoot): string
    {
        if (! $this->sponsorCanPay()) {
            throw new SponsorOutOfFunds("La cuenta patrocinadora {$this->sponsorAddress()} no tiene XLM para la comisión.");
        }

        $sealer = $this->sealer()->getAccountId();

        // Una transacción pendiente de la selladora a la vez: la toma desde que existe, antes de
        // enviarla, así un envío sin respuesta (NetworkUnavailable) sigue con el turno: pudo llegar.
        $feeBump = SealerTurn::exclusively($sealer, function (SealerTurn $turn) use ($worksiteReference, $merkleRoot) {
            $turn->waitIfTaken(fn (string $txHash) => $this->rpc->transaction($txHash)->status === GetTransactionResponse::STATUS_NOT_FOUND);

            $feeBump = $this->signedSeal($worksiteReference, $merkleRoot);
            $turn->takeFor($this->hashOf($feeBump), now()->addSeconds(SealingRetryPolicy::TRANSACTION_VALIDITY_SECONDS + SealerTurn::EXPIRY_GRACE_SECONDS));

            return $feeBump;
        });
        $txHash = $this->hashOf($feeBump);

        $sent = $this->rpc->send($feeBump);

        if ($sent->status === SendTransactionResponse::STATUS_PENDING || $sent->status === SendTransactionResponse::STATUS_DUPLICATE) {
            return $txHash;
        }

        if (SendRefusal::meansSealerBusy($sent)) {
            SealerTurn::holdAfterRefusal($sealer, $txHash);

            throw new SealingNetworkBusy(SealerTurn::RETRY_SECONDS, 'La cuenta selladora tiene otra transacción pendiente ('.SendRefusal::describe($sent).'): este sello espera su turno.');
        }

        SealerTurn::release($sealer, $txHash);

        throw new SealingNetworkError('La red rechazó el sello ('.SendRefusal::describe($sent).').');
    }

    public function transactionStatus(string $txHash): ?NetworkSeal
    {
        $transaction = $this->rpc->transaction($txHash);

        if ($transaction->status !== GetTransactionResponse::STATUS_NOT_FOUND) {
            // Entró en un ledger, o la red la rechazó: la selladora queda libre para el siguiente sello.
            SealerTurn::release($this->sealer()->getAccountId(), $txHash);
        }

        return match ($transaction->status) {
            GetTransactionResponse::STATUS_SUCCESS => new NetworkSeal(
                $transaction->ledger,
                CarbonImmutable::createFromTimestamp((int) $transaction->createdAt),
                $txHash,
                $this->feeCharged($transaction),
            ),
            GetTransactionResponse::STATUS_NOT_FOUND => null,
            default => throw new SealingNetworkError("La transacción {$txHash} falló en la red ({$transaction->status})."),
        };
    }

    public function findSeal(string $merkleRoot): ?NetworkSeal
    {
        $root = XdrSCVal::forBytes(hex2bin($merkleRoot));

        // DataKey::Seal(raíz) del contrato, leída como entrada del ledger: vale viva o archivada.
        $entry = $this->rpc->contractData($this->contractId, XdrSCVal::forVec([XdrSCVal::forSymbol('Seal'), $root]));

        if ($entry === null) {
            return null; // la raíz no está sellada
        }

        $fields = [];
        foreach ($entry->getLedgerEntryDataXdr()->contractData->val->map ?? [] as $field) {
            $fields[$field->key->sym] = $field->val;
        }
        $ledger = $fields['ledger']->u32;

        // La que la selló, por su evento "sealed" en ese ledger: tras un envío sin
        // respuesta, la última anotada puede no ser esa (US-021, US-023).
        $txHash = $this->rpc->eventTransactionHash($this->contractId, [XdrSCVal::forSymbol('sealed'), $root], $ledger);

        return new NetworkSeal(
            $ledger,
            CarbonImmutable::createFromTimestamp((int) $fields['sealed_at']->u64),
            $txHash,
            $txHash === null ? null : $this->feeCharged($this->rpc->transaction($txHash)),
        );
    }

    public function contractId(): string
    {
        return $this->contractId;
    }

    public function sponsorCanPay(): bool
    {
        return $this->sponsorBalance() >= $this->sponsorMinBalanceXlm * self::STROOPS_PER_XLM;
    }

    public function sponsorBalance(): int
    {
        return $this->rpc->accountBalance($this->sponsorAddress());
    }

    public function contractLifetime(): ContractLifetime
    {
        $instanceKey = XdrLedgerKey::forContractData(XdrSCAddress::forContractId($this->contractId), XdrSCVal::forLedgerKeyContractInstance(), XdrContractDataDurability::PERSISTENT());
        $answer = $this->rpc->ledgerEntry($instanceKey);
        $instance = $answer->entries[0] ?? throw new SealingNetworkError("El contrato {$this->contractId} no existe en la red.");

        // La instancia dice qué código ejecuta: el WASM que subió la tesorería.
        $wasmId = $instance->getLedgerEntryDataXdr()->contractData->val->instance->executable->wasmIdHex;
        $code = $this->rpc->ledgerEntry(XdrLedgerKey::forContractCode(hex2bin($wasmId)))->entries[0]
            ?? throw new SealingNetworkError("El código {$wasmId} del contrato no existe en la red.");

        return new ContractLifetime($answer->latestLedger, $instance->liveUntilLedgerSeq, $code->liveUntilLedgerSeq);
    }

    public function sponsorAddress(): string
    {
        return $this->sponsor()->getAccountId();
    }

    /** What the fee bump charged the sponsor, refunds of unused resources already out; null if the network no longer has it. */
    private function feeCharged(GetTransactionResponse $transaction): ?int
    {
        $result = $transaction->status === GetTransactionResponse::STATUS_SUCCESS ? $transaction->getXdrTransactionResult() : null;

        return $result === null ? null : (int) $result->feeCharged->toString();
    }

    /**
     * seal(obra, raíz), firmada por la selladora y envuelta en el fee bump de
     * la patrocinadora, lista para enviar.
     *
     * @throws RootAlreadySealed
     * @throws SealingNetworkError
     */
    private function signedSeal(string $worksiteReference, string $merkleRoot): FeeBumpTransaction
    {
        [$transaction, $simulation] = $this->simulate('seal', [XdrSCVal::forBytes(hex2bin($worksiteReference)), XdrSCVal::forBytes(hex2bin($merkleRoot))]);

        if ($simulation->resultError !== null) {
            if (str_contains($simulation->resultError, self::HASH_ALREADY_REGISTERED)) {
                throw new RootAlreadySealed($merkleRoot);
            }

            throw new SealingNetworkError('La simulación del sello falló: '.strtok($simulation->resultError, "\n"));
        }

        $transaction->setSorobanTransactionData($simulation->getTransactionData());
        $transaction->addResourceFee($simulation->getMinResourceFee());
        $transaction->setSorobanAuth($simulation->getSorobanAuth());
        $transaction->sign($this->sealer(), $this->network());

        // El fee bump cubre la comisión completa de la transacción interna,
        // incluida la de recursos de Soroban. Stellar cobra lo consumido.
        // La base es esa comisión completa, no la mínima de 100 stroops, a
        // propósito: el SDK la multiplica por (operaciones + 1), así que la puja
        // de inclusión queda cerca de la comisión del sello, por encima de
        // cualquier pico de congestión. En congestión cada transacción paga la
        // menor puja que entró al ledger, no la suya: la puja alta asegura la
        // entrada sin subir el costo (docs/sellado-en-stellar.md, sección 10).
        $feeBump = (new FeeBumpTransactionBuilder($transaction))
            ->setBaseFee(max(100, $transaction->getFee()))
            ->setFeeAccount($this->sponsorAddress())
            ->build();
        $feeBump->sign($this->sponsor(), $this->network());

        return $feeBump;
    }

    /** The hash the network gives the transaction: known before sending it. */
    private function hashOf(FeeBumpTransaction $feeBump): string
    {
        return bin2hex($feeBump->hash($this->network()));
    }

    /** @return array{0: Transaction, 1: SimulateTransactionResponse} */
    private function simulate(string $function, array $arguments): array
    {
        $sealer = $this->sealer()->getAccountId();
        $entry = $this->rpc->account($sealer)
            ?? throw new SealingNetworkError("La cuenta selladora {$sealer} no existe en la red.");

        // Vale 4 minutos y no más: si se da por perdida y se reenvía, esta ya no puede entrar (US-021).
        $validUntil = new DateTime('@'.(time() + SealingRetryPolicy::TRANSACTION_VALIDITY_SECONDS));

        $operation = (new InvokeHostFunctionOperationBuilder(new InvokeContractHostFunction($this->contractId, $function, $arguments)))->build();
        $transaction = (new TransactionBuilder(new Account($sealer, $entry->seqNum->sequenceNumber)))
            ->addOperation($operation)
            ->setTimeBounds(new TimeBounds(new DateTime('@0'), $validUntil))
            ->build();

        return [$transaction, $this->rpc->simulate($transaction)];
    }

    private function sealer(): KeyPair
    {
        return KeyPair::fromSeed($this->sealerSecret);
    }

    private function sponsor(): KeyPair
    {
        return KeyPair::fromSeed($this->sponsorSecret);
    }

    private function network(): Network
    {
        return new Network($this->networkPassphrase);
    }
}
