<?php

namespace App\Domain\Reports;

/**
 * It. 46e (R-PRIV-05): what the phone says it blurred in a photo before
 * computing its hash — the faces the detector found and were kept blurred,
 * the proposed blurs removed (the Bandeja marks them) and the zones blurred
 * by hand. The server cannot check it: it is what the Administrador sees.
 */
final class Blurring
{
    public const MAX_ZONES = 50;

    private function __construct(
        public readonly int $faces,
        public readonly int $dismissed,
        public readonly int $manual,
    ) {}

    /** Null when it is not one: three whole numbers, from 0 to 50. */
    public static function fromArray(mixed $value): ?self
    {
        if (! is_array($value)) {
            return null;
        }
        foreach (['faces', 'dismissed', 'manual'] as $key) {
            if (! is_int($value[$key] ?? null) || $value[$key] < 0 || $value[$key] > self::MAX_ZONES) {
                return null;
            }
        }

        return new self($value['faces'], $value['dismissed'], $value['manual']);
    }

    /** @return array{faces: int, dismissed: int, manual: int}|null */
    public static function presentOf(Evidence $evidence): ?array
    {
        return $evidence->blurred_faces === null ? null : [
            'faces' => $evidence->blurred_faces,
            'dismissed' => $evidence->dismissed_faces,
            'manual' => $evidence->blurred_by_hand,
        ];
    }
}
