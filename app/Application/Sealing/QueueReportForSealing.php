<?php

namespace App\Application\Sealing;

use App\Domain\Reports\Report;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealStatus;
use App\Jobs\SealReport;

/** US-020b: un reporte ya guardado ("Recibida") pasa a "En Cola" con su trabajo de sellado despachado. */
class QueueReportForSealing
{
    public function handle(Report $report): void
    {
        ReportSeal::query()
            ->where('report_id', $report->id)
            ->update(['status' => SealStatus::Queued, 'queued_at' => now()]);

        SealReport::dispatch(tenant()->getTenantKey(), $report->id);
    }
}
