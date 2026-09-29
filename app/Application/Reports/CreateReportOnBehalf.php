<?php

namespace App\Application\Reports;

use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Exceptions\SuperAdminNotAuthorized;
use App\Domain\Organization\SuperAdminAuthorization;
use App\Domain\Organization\User;
use App\Domain\Reports\Report;
use App\Models\User as SuperAdmin;
use Illuminate\Support\Facades\DB;

/**
 * US-042-SEC: el Super Administrador crea un reporte en una organización
 * que lo autorizó (R-SA-02). Es un reporte como cualquier otro — las
 * mismas reglas de CreateReport —, firmado por el miembro que lo
 * representa en la organización, y queda en el log de auditoría. Corre en
 * el contexto de la organización.
 */
class CreateReportOnBehalf
{
    public function handle(SuperAdmin $superAdmin, NewReport $input): Report
    {
        if (SuperAdminAuthorization::inForce() === null) {
            throw new SuperAdminNotAuthorized;
        }

        // El log vive en la base central; si falla, el reporte no queda.
        return DB::transaction(function () use ($superAdmin, $input) {
            $report = (new CreateReport)->handle(User::superAdminDelegate(), $input);

            AuditLog::record(
                action: 'report.created_by_super_admin',
                organizationId: tenant()->getTenantKey(),
                actorType: 'super_admin',
                actorId: (string) $superAdmin->id,
                actorName: $superAdmin->name,
                after: ['report_id' => $report->id, 'secop_contract_id' => $input->secopContractId],
            );

            return $report;
        });
    }
}
