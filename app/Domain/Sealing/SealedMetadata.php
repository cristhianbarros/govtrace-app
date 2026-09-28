<?php

namespace App\Domain\Sealing;

use App\Domain\Reports\Report;

/**
 * US-020b / R-PRIV-03: la última hoja del árbol de un reporte es el hash de
 * este JSON, así el contexto geográfico y temporal también queda sellado. Va
 * el seudónimo del veedor, nunca su ID.
 *
 * Canónico (D6), para que el navegador lo recomponga byte a byte: claves
 * ordenadas, sin espacios, UTF-8 sin escapar (tampoco "/" ni U+2028/2029),
 * coordenadas como texto con 7 decimales y la hora de captura en UTC.
 */
final class SealedMetadata
{
    private function __construct(public readonly string $json) {}

    /** @param  array<string, string|null>  $fields */
    public static function fromFields(array $fields): self
    {
        ksort($fields, SORT_STRING);

        return new self(json_encode(
            $fields,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR,
        ));
    }

    public static function forReport(Report $report, string $pseudonym): self
    {
        return self::fromFields([
            'captured_at' => $report->captured_at->toImmutable()->utc()->format('Y-m-d\TH:i:s\Z'),
            'classification' => $report->classification->value,
            'comment' => $report->comment,
            'latitude' => number_format((float) $report->latitude, 7, '.', ''),
            'longitude' => number_format((float) $report->longitude, 7, '.', ''),
            'pseudonym' => $pseudonym,
        ]);
    }

    public function sha256(): string
    {
        return hash('sha256', $this->json);
    }
}
