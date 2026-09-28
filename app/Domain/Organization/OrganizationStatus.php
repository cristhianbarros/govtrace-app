<?php

namespace App\Domain\Organization;

/**
 * Whether an organization can work (US-003a). Suspended: none of its users
 * gets in and it takes no new reports, but its public map stays online,
 * read-only, with a notice (R-AUD-01). Dar de baja (US-003b) is it. 33.
 */
enum OrganizationStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Suspended => 'Suspendida',
        };
    }

    /** What every public page of the organization shows while it lasts. */
    public function publicNotice(): ?string
    {
        return match ($this) {
            self::Suspended => '⚠️ Esta organización se encuentra suspendida temporalmente. Sus evidencias publicadas siguen disponibles solo para consulta.',
            self::Active => null,
        };
    }
}
