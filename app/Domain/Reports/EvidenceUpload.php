<?php

namespace App\Domain\Reports;

use finfo;

/**
 * One file as it reached the server, with the SHA-256 the phone computed
 * for it before sending (R-HASH-01).
 */
final class EvidenceUpload
{
    private ?string $serverSha256 = null;

    public function __construct(
        public readonly string $path,
        public readonly string $mimeType,
        public readonly int $sizeBytes,
        public readonly ?string $declaredSha256,
    ) {}

    /** A file on disk; without $declaredSha256, it declares its own hash (as an honest phone would). */
    public static function fromPath(string $path, ?string $declaredSha256 = null): self
    {
        return new self(
            $path,
            (string) (new finfo(FILEINFO_MIME_TYPE))->file($path),
            (int) filesize($path),
            $declaredSha256 ?? hash_file('sha256', $path),
        );
    }

    public function kind(): ?EvidenceKind
    {
        return EvidenceKind::fromMimeType($this->mimeType);
    }

    /** Recomputed here, from the bytes that actually arrived. */
    public function serverSha256(): string
    {
        return $this->serverSha256 ??= hash_file('sha256', $this->path);
    }

    public function hashMatches(): bool
    {
        return $this->declaredSha256 !== null
            && hash_equals(strtolower($this->declaredSha256), $this->serverSha256());
    }
}
