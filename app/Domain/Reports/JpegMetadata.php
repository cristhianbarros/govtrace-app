<?php

namespace App\Domain\Reports;

/**
 * R-PRIV-06 / US-026: whether a JPEG still carries EXIF or XMP — where a
 * phone writes its GPS position, its model and the capture time. The PWA
 * strips them before hashing (it. 16); the server can't strip them without
 * changing the sealed bytes, so it refuses such a photo instead: nothing
 * sealed and published can carry the phone's location.
 *
 * Reads only the segments before the image data (SOS), as bytes.
 */
final class JpegMetadata
{
    private const EXIF = "Exif\0\0";

    /** XMP, and the continuation of a long one ("Extended XMP"): both can carry the GPS position. */
    private const XMP = ["http://ns.adobe.com/xap/1.0/\0", "http://ns.adobe.com/xmp/extension/\0"];

    public static function carriesMetadata(string $path): bool
    {
        $jpeg = (string) file_get_contents($path);

        if (! str_starts_with($jpeg, "\xFF\xD8")) {
            return false; // not a JPEG: other rules decide
        }

        for ($offset = 2; $offset + 4 <= strlen($jpeg) && $jpeg[$offset] === "\xFF";) {
            $marker = ord($jpeg[$offset + 1]);
            if ($marker === 0xFF) {
                $offset++; // a fill byte before the marker: allowed, and it must not hide one

                continue;
            }
            if ($marker === 0xDA) {
                return false; // start of scan: from here on, the image itself
            }

            $length = unpack('n', substr($jpeg, $offset + 2, 2))[1];

            if ($marker === 0xE1 && self::isMetadata(substr($jpeg, $offset + 4, max(0, $length - 2)))) {
                return true;
            }

            $offset += 2 + $length;
        }

        return false;
    }

    private static function isMetadata(string $app1): bool
    {
        foreach ([self::EXIF, ...self::XMP] as $signature) {
            if (str_starts_with($app1, $signature)) {
                return true;
            }
        }

        return false;
    }
}
