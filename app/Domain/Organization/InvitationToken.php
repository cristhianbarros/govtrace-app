<?php

namespace App\Domain\Organization;

use Illuminate\Support\Str;

/**
 * The link every "set your password" email carries (US-002, US-005) —
 * only the hash is ever persisted (User::$invitation_token_hash), so a
 * leaked database dump doesn't hand out working invitation links.
 */
final class InvitationToken
{
    private function __construct(
        public readonly string $plain,
        public readonly string $hash,
    ) {}

    public static function generate(): self
    {
        $plain = Str::random(64);

        return new self($plain, self::hashOf($plain));
    }

    public static function hashOf(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
