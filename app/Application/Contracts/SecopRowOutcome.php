<?php

namespace App\Application\Contracts;

/**
 * What happened to one SECOP II row in ProcessSecopContractRow. The sync
 * job tallies these into the run record that US-014's health panel reads.
 */
enum SecopRowOutcome: string
{
    case Inserted = 'inserted';
    case Updated = 'updated';

    /** US-032: not a works contract. */
    case NotWorks = 'not_works';

    /** R-INT-03: its location doesn't match DIVIPOLA — discarded and reported. */
    case Unmatched = 'unmatched';

    /** R-SEC-02 / R-AUD-06: matched, but no active organization watches it. */
    case OutOfTerritory = 'out_of_territory';

    public function isSaved(): bool
    {
        return $this === self::Inserted || $this === self::Updated;
    }
}
