<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Publication\OpenData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /open-data.csv and /open-data.json (US-052-RPT): the published
 * evidences and their seals, without a session (R-VER-02). Still there after
 * a decommission (US-003b): the evidence stays verifiable.
 */
class OpenDataController extends Controller
{
    public function __invoke(OpenData $openData, string $format): StreamedResponse|JsonResponse
    {
        $now = now()->timezone('America/Bogota');
        $name = tenant()->subdomain()."-datos-abiertos-{$now->toDateString()}.{$format}";
        $records = $openData->records();

        if ($format === 'json') {
            return response()->json([
                'organizacion' => tenant()->displayName(),
                'generado_en' => $now->toIso8601String(),
                'red' => config('stellar.network_passphrase'),
                'registros' => $records,
            ], 200, ['Content-Disposition' => "attachment; filename={$name}"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return response()->streamDownload(function () use ($records) {
            $out = fopen('php://output', 'w');
            fputcsv($out, OpenData::FIELDS, ',', '"', '');
            foreach ($records as $record) {
                fputcsv($out, array_values($record), ',', '"', '');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
