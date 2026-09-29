<?php

namespace App\Domain\Organization;

/**
 * Whether an organization can work (US-003a, US-003b). Suspended: none of
 * its users gets in and it takes no new reports, but its public map stays
 * online, read-only, with a notice (R-AUD-01). Decommissioned (dada de
 * baja): definitive — the same, and its map goes offline too; its evidence
 * stays verifiable and nothing is deleted, but its files after 5 years.
 */
enum OrganizationStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Decommissioned = 'decommissioned';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Suspended => 'Suspendida',
            self::Decommissioned => 'Dada de baja',
        };
    }

    /** What every public page of the organization shows while it lasts. */
    public function publicNotice(): ?string
    {
        return match ($this) {
            self::Suspended => '⚠️ Esta organización se encuentra suspendida temporalmente. Sus evidencias publicadas siguen disponibles solo para consulta.',
            self::Decommissioned => '⚠️ Esta organización fue dada de baja. Su mapa público ya no está disponible; sus evidencias selladas siguen verificables en el validador.',
            self::Active => null,
        };
    }
}
