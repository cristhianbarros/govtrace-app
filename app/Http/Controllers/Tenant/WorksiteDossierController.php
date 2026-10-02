<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Dossier\DossierArchive;
use App\Application\Dossier\WorksiteDossier;
use App\Domain\Audit\AuditLog;
use App\Domain\Worksites\Worksite;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * GET /worksites/{worksite}/dossier.zip (US-056-LEG): the Administrador's
 * dossier of a worksite — what the veeduría takes to the contracting entity
 * and to the Contraloría. Each download goes to the audit log (R-LEG-04):
 * it is how we learn, in production, whether the dossier gets used.
 */
class WorksiteDossierController extends Controller
{
    public function __invoke(Request $request, string $worksite, WorksiteDossier $dossiers, DossierArchive $archive): BinaryFileResponse
    {
        $record = Worksite::byPublicId($worksite);
        $dossier = $dossiers->of($record);
        ['path' => $path, 'files' => $files] = $archive->build($dossier);
        $administrator = $request->user('tenant');

        AuditLog::record(
            action: 'dossier.downloaded',
            organizationId: tenant()->getTenantKey(),
            actorType: 'organization_admin',
            actorId: (string) $administrator->id,
            actorName: $administrator->name,
            after: ['worksite_id' => $record->id, 'worksite' => $dossier['worksite']['name'], 'evidences' => count($dossier['evidences']), 'files' => $files],
        );

        $name = 'expediente-'.Str::slug($dossier['worksite']['name']).'-'.now()->timezone('America/Bogota')->toDateString().'.zip';

        return response()->download($path, $name, ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}
