<?php

namespace App\Domain\Configuration;

use App\Domain\Reports\EvidenceSet;
use App\Domain\Reports\GpsReading;
use App\Domain\Reports\SuspiciousCaptureTime;
use App\Domain\Worksites\FirstTouch;

/**
 * R-CFG-02: what stays fixed in the code, on purpose — the evidence rules
 * the whole platform certifies by. The panel shows them read-only, and
 * they take their values from the rules themselves.
 */
final class FixedParameters
{
    /** @return list<array{key: string, label: string, value: string}> */
    public static function all(): array
    {
        $maxMegabytes = EvidenceSet::MAX_BYTES_PER_FILE / 1024 / 1024;

        return [
            ['key' => 'gps_max_accuracy_meters', 'label' => 'Precisión mínima del GPS', 'value' => GpsReading::MAX_ACCURACY_METERS.' m'],
            ['key' => 'files_per_report', 'label' => 'Archivos por reporte', 'value' => '1 a '.EvidenceSet::MAX_PHOTOS." fotos o 1 PDF, de hasta {$maxMegabytes} MB cada uno"],
            ['key' => 'offline_validity_days', 'label' => 'Vigencia de reportes sin conexión', 'value' => SuspiciousCaptureTime::OFFLINE_VALIDITY_DAYS.' días'],
            ['key' => 'anchor_max_accuracy_meters', 'label' => 'Precisión del GPS para fijar la ubicación de una obra', 'value' => FirstTouch::MAX_ACCURACY_METERS.' m'],
        ];
    }
}
