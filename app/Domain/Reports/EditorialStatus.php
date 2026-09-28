<?php

namespace App\Domain\Reports;

/**
 * What the public sees of an evidence (US-036, US-037), apart from its
 * sealing status. Every evidence is born Hidden; only the Administrador of
 * its organization moves it, one at a time.
 *
 *   Hidden ──publish──▶ Published ──withdraw──▶ Withdrawn (a tombstone)
 *     └────reject────▶ Rejected (never public, no tombstone)
 */
enum EditorialStatus: string
{
    case Hidden = 'hidden';
    case Published = 'published';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Hidden => 'Oculto',
            self::Published => 'Publicado',
            self::Rejected => 'Rechazado',
            self::Withdrawn => 'Retirado',
        };
    }

    /** R-USR-02: what the veedor sees in "Mis Reportes" (US-010). */
    public function veedorLabel(): string
    {
        return match ($this) {
            self::Hidden => 'En Revisión',
            self::Published => 'Publicado',
            self::Rejected => 'Rechazada',
            self::Withdrawn => 'Retirado',
        };
    }
}
