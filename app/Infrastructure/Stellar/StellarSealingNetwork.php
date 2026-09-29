<?php

namespace App\Infrastructure\Stellar;

use App\Application\Sealing\Exceptions\RootAlreadySealed;
use App\Application\Sealing\Exceptions\SealingNetworkError;
use App\Application\Sealing\Exceptions\SponsorOutOfFunds;
use App\Application\Sealing\NetworkSeal;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Sealing\SealingRetryPolicy;
use Carbon\CarbonImmutable;
use DateTime;
use Soneso\StellarSDK\Account;
use Soneso\StellarSDK\Crypto\KeyPair;
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
use Soneso\StellarSDK\Xdr\XdrSCVal;

/**
 * El sellado en Stellar (US-020b, D5): invoca seal(obra, raíz) del contrato
 * de contracts/sealing. La transacción la firma la cuenta selladora y la
 * envuelve en un fee bump la cuenta patrocinadora, que es la única que paga.
 * El veedor no ve nada de esto (R-BLK-01).
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
        $feeBump = (new FeeBumpTransactionBuilder($transaction))
            ->setBaseFee(max(100, $transaction->getFee()))
            ->setFeeAccount($this->sponsorAddress())
            ->build();
        $feeBump->sign($this->sponsor(), $this->network());

        $sent = $this->rpc->send($feeBump);

        if ($sent->status !== SendTransactionResponse::STATUS_PENDING && $sent->status !== SendTransactionResponse::STATUS_DUPLICATE) {
            throw new SealingNetworkError("La red rechazó el sello ({$sent->status}): {$sent->errorResultXdr}");
        }

        return $sent->hash;
    }

    public function transactionStatus(string $txHash): ?NetworkSeal
    {
        $transaction = $this->rpc->transaction($txHash);

        return match ($transaction->status) {
            GetTransactionResponse::STATUS_SUCCESS => new NetworkSeal(
                $transaction->ledger,
                CarbonImmutable::createFromTimestamp((int) $transaction->createdAt),
                $txHash,
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

        return new NetworkSeal(
            $ledger,
            CarbonImmutable::createFromTimestamp((int) $fields['sealed_at']->u64),
            // La que la selló, por su evento "sealed" en ese ledger: tras un envío sin
            // respuesta, la última anotada puede no ser esa (US-021, US-023).
            $this->rpc->eventTransactionHash($this->contractId, [XdrSCVal::forSymbol('sealed'), $root], $ledger),
        );
    }

    public function contractId(): string
    {
        return $this->contractId;
    }

    public function sponsorCanPay(): bool
    {
        return $this->rpc->accountBalance($this->sponsorAddress()) >= $this->sponsorMinBalanceXlm * self::STROOPS_PER_XLM;
    }

    public function sponsorAddress(): string
    {
        return $this->sponsor()->getAccountId();
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
