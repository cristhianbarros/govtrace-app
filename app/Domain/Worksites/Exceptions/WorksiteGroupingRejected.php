<?php

namespace App\Domain\Worksites\Exceptions;

use DomainException;

/**
 * Why contracts were not grouped in a worksite (US-045-INT). $field names
 * the part of the request the problem is about — the key the endpoint
 * answers with.
 */
class WorksiteGroupingRejected extends DomainException
{
    private function __construct(string $message, public readonly string $field)
    {
        parent::__construct($message);
    }

    public static function nameRequired(): self
    {
        return new self('La ficha necesita un nombre.', 'name');
    }

    public static function nameTooLong(int $maxLength): self
    {
        return new self("El nombre de la ficha admite máximo {$maxLength} caracteres.", 'name');
    }

    public static function needsTwoContracts(): self
    {
        return new self('Agrupar exige al menos dos contratos distintos.', 'secop_contract_ids');
    }

    public static function unknownContract(string $secopContractId): self
    {
        return new self("No existe el contrato {$secopContractId} en SECOP II.", 'secop_contract_ids');
    }

    public static function outsideTerritory(string $secopContractId): self
    {
        return new self("El contrato {$secopContractId} no es del territorio de la organización.", 'secop_contract_ids');
    }

    /** @param  list<string>  $secopContractIds */
    public static function reportsInSeveralWorksites(array $secopContractIds): self
    {
        $listed = implode(', ', array_slice($secopContractIds, 0, -1)).' y '.end($secopContractIds);

        return new self("Los contratos {$listed} ya tienen reportes en fichas distintas, y un reporte no cambia de ficha.", 'secop_contract_ids');
    }

    /** A veedor sent the first report of one of the contracts at that very moment. */
    public static function reportedMeanwhile(): self
    {
        return new self('Un veedor acaba de reportar sobre uno de estos contratos. Vuelva a intentar la agrupación.', 'secop_contract_ids');
    }
}
