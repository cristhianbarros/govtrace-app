<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Reports\EvidenceExport;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** GET /export.csv (US-050-RPT): the Administrador's export of the organization's worksites and evidences. */
class ExportController extends Controller
{
    public function __invoke(EvidenceExport $export): StreamedResponse
    {
        $name = tenant()->subdomain().'-evidencias-'.now()->timezone('America/Bogota')->toDateString().'.csv';

        return response()->streamDownload(function () use ($export) {
            $out = fopen('php://output', 'w');
            // La marca UTF-8: así una hoja de cálculo lee bien las tildes.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, EvidenceExport::COLUMNS, ',', '"', '');
            foreach ($export->rows() as $row) {
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
