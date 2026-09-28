<?php

namespace App\Domain\Organization;

use App\Domain\Organization\Exceptions\LogoRejected;

/**
 * US-007: the organization's logo, checked before it's kept. PNG, JPG or
 * SVG — by what the file IS, not by its name —, 2 MB at most, and a raster
 * image of 128x128 px at least. An SVG is a drawing, not pixels: it scales
 * without losing quality, so no minimum applies; it's kept only after
 * SvgSanitizer.
 */
final class OrganizationLogo
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    private const MIN_PIXELS = 128;

    private const RASTER = ['image/png' => 'png', 'image/jpeg' => 'jpg'];

    private function __construct(
        public readonly string $contents,
        public readonly string $extension,
    ) {}

    public static function fromFile(string $path): self
    {
        $contents = (string) file_get_contents($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);

        if (isset(self::RASTER[$mime])) {
            return self::raster($contents, self::RASTER[$mime]);
        }

        if (in_array($mime, ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain'], true) && str_contains($contents, '<svg')) {
            self::assertSize($contents);

            return new self((new SvgSanitizer)->sanitize($contents), 'svg');
        }

        throw LogoRejected::invalidFormat();
    }

    public function mimeType(): string
    {
        return match ($this->extension) {
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
        };
    }

    private static function raster(string $contents, string $extension): self
    {
        self::assertSize($contents);

        $size = getimagesizefromstring($contents);
        if ($size === false) {
            throw LogoRejected::invalidFormat();
        }
        if ($size[0] < self::MIN_PIXELS || $size[1] < self::MIN_PIXELS) {
            throw LogoRejected::tooSmall();
        }

        return new self($contents, $extension);
    }

    private static function assertSize(string $contents): void
    {
        if (strlen($contents) > self::MAX_BYTES) {
            throw LogoRejected::tooLarge();
        }
    }
}
