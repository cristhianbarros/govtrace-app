<?php

namespace App\Infrastructure\Stellar;

use RuntimeException;

/**
 * D13: in the main network the Stellar RPC comes from a provider, with two
 * endpoints — the private one for the server (rpc_url) and a read-only one,
 * restricted to GovTrace's domain, for the validator in the browser
 * (public_rpc_url, US-024). The application refuses to start without the
 * public one, or with the private one in its place: its token would be in
 * every browser. Testnet and the local network have their own public RPC.
 */
final class MainnetConfiguration
{
    public const PASSPHRASE = 'Public Global Stellar Network ; September 2015';

    /** @param  array<string, mixed>  $stellar  config('stellar') */
    public static function assertSafe(array $stellar): void
    {
        if (($stellar['network_passphrase'] ?? null) !== self::PASSPHRASE) {
            return;
        }

        $public = self::normalized($stellar['public_rpc_url'] ?? null);

        if ($public === null) {
            throw new RuntimeException('Falta STELLAR_PUBLIC_RPC_URL: en la red principal, el validador del navegador necesita un endpoint de solo lectura restringido al dominio de GovTrace (D13).');
        }

        if ($public === self::normalized($stellar['rpc_url'] ?? null)) {
            throw new RuntimeException('STELLAR_PUBLIC_RPC_URL no puede ser el mismo endpoint que STELLAR_RPC_URL: el token del proveedor quedaría a la vista en el navegador (D13).');
        }
    }

    /** The same endpoint written two ways compares equal: the host has no case, and a final slash changes nothing. */
    private static function normalized(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $parts = parse_url(trim($url));

        return strtolower(($parts['scheme'] ?? '').'://'.($parts['host'] ?? '')).(isset($parts['port']) ? ":{$parts['port']}" : '').rtrim($parts['path'] ?? '', '/').(isset($parts['query']) ? "?{$parts['query']}" : '');
    }
}
