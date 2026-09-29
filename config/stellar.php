<?php

/*
 * Sellado en Stellar (it. 12-13, D4/D5). En desarrollo, `make contract-deploy`
 * llena estos valores en el .env local con la red standalone de Docker. En
 * producción, las llaves secretas se custodian fuera del código de la
 * aplicación (decisión D11 de specs/PLAN.md); nunca van en el repositorio ni
 * en .env.example (R-BLK-04).
 */
return [
    'rpc_url' => env('STELLAR_RPC_URL', 'http://stellar:8000/rpc'),
    'network_passphrase' => env('STELLAR_NETWORK_PASSPHRASE', 'Standalone Network ; February 2017'),
    'sealing_contract_id' => env('STELLAR_SEALING_CONTRACT_ID'),

    // Firma la invocación de seal(); no necesita XLM propios.
    'sealer_secret' => env('STELLAR_SEALER_SECRET'),

    // Paga todas las comisiones con fee bump (D5).
    'sponsor_secret' => env('STELLAR_SPONSOR_SECRET'),

    // Por debajo de esto el sellado se pausa: la reserva mínima de una cuenta
    // es 1 XLM y un sello cuesta ~0,07 XLM (medido en la red local, it. 13).
    'sponsor_min_balance_xlm' => (int) env('STELLAR_SPONSOR_MIN_BALANCE_XLM', 2),

    // US-024: el RPC que consulta el navegador en el validador público — uno
    // público y con CORS, nunca el de dentro de Docker (rpc_url). Sale de la
    // red; en la pública hay que elegir un proveedor
    // (https://developers.stellar.org/docs/data/apis/rpc/providers).
    'public_rpc_url' => env('STELLAR_PUBLIC_RPC_URL') ?: match (env('STELLAR_NETWORK_PASSPHRASE')) {
        'Test SDF Network ; September 2015' => 'https://soroban-testnet.stellar.org',
        'Public Global Stellar Network ; September 2015' => null,
        default => 'http://127.0.0.1:8100/rpc', // la red local de make stellar-up
    },

    // US-023 / US-025: el botón "Ver en Stellar Expert" del recibo (sus rutas
    // /tx/{hash} y /ledger/{n}). Sale de la red; la local no tiene explorador
    // público, y el recibo va sin el botón.
    'explorer_url' => env('STELLAR_EXPLORER_URL') ?: match (env('STELLAR_NETWORK_PASSPHRASE')) {
        'Test SDF Network ; September 2015' => 'https://stellar.expert/explorer/testnet',
        'Public Global Stellar Network ; September 2015' => 'https://stellar.expert/explorer/public',
        default => null,
    },
];
