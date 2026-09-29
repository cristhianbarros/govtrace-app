<?php

namespace App\Application\Publication;

/**
 * US-024: what the browser needs to read a seal on the Stellar network by
 * itself, for the public validator — its RPC (a public one), the network,
 * the sealing contracts (R-MNT-01: GovTrace's on that network, from the
 * same list the independent verifier trusts, tools/verify/contracts.json,
 * plus the configured one) and the explorer.
 */
final class StellarForBrowser
{
    /** @return array{rpc_url: ?string, network_passphrase: string, contracts: list<string>, explorer_url: ?string} */
    public static function props(): array
    {
        $passphrase = (string) config('stellar.network_passphrase');
        $networks = json_decode((string) file_get_contents(base_path('tools/verify/contracts.json')), true);

        return [
            'rpc_url' => config('stellar.public_rpc_url'),
            'network_passphrase' => $passphrase,
            'contracts' => array_values(array_unique(array_filter([
                ...($networks[$passphrase]['contracts'] ?? []),
                config('stellar.sealing_contract_id'),
            ]))),
            'explorer_url' => config('stellar.explorer_url'),
        ];
    }
}
