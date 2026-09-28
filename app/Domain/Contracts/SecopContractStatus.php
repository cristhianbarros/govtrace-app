<?php

namespace App\Domain\Contracts;

/**
 * R-SEC-07: how GovTrace reads the "estado_contrato" SECOP II publishes.
 * The discovery spoke of "Celebrado", "Adjudicado", "Terminado" and
 * "Liquidado"; none of those exist among the 53.398 works contracts of
 * SECOP II (dataset jbjy-vk9h, 2026-09-27). This is the approved table of
 * equivalences over the values that do — lowercase, because SECOP mixes
 * cases ("terminado") and the comparison ignores it.
 *
 * The contract keeps SECOP's own text untouched (R-SEC-01); only the
 * queries read it through this table. A status not listed here is never
 * reportable.
 */
final class SecopContractStatus
{
    /**
     * Always reportable (US-016, US-008). Suspendido included on purpose:
     * paralyzed works — the "elefantes blancos" — are where citizen
     * evidence matters most.
     */
    public const ACTIVE = ['en ejecución', 'modificado', 'aprobado', 'cedido', 'suspendido'];

    /** Reportable only within closed_contract_report_window_months of their end date. */
    public const CLOSED = ['terminado', 'cerrado'];

    /** Still running per SECOP — past its end date, the worksite is "en riesgo" (US-034). */
    public const IN_EXECUTION = ['en ejecución', 'modificado'];
}
