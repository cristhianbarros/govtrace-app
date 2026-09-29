#!/usr/bin/env node
// Verificador independiente de GovTrace (US-026): comprueba que un archivo
// descargado es exactamente el que se selló en la red Stellar, sin pasar por
// GovTrace. Solo Node.js 20 o más nuevo; nada que instalar. Ver README.md.

import { readFile } from 'node:fs/promises';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { parseArgs } from 'node:util';
import { stellarRpc } from './lib/rpc.mjs';
import { CannotVerify, parseProof, verifyEvidence } from './lib/verify.mjs';

export const EXIT = { AUTHENTIC: 0, MISMATCH: 1, CANNOT_VERIFY: 2 };

const USAGE = 'Uso: node verify.mjs <archivo> <archivo.prueba.json> [--rpc URL] [--contract C…]';

async function read(path, encoding) {
    try {
        return await readFile(path, encoding);
    } catch (error) {
        throw new CannotVerify(`No se pudo leer ${path} (${error.code ?? error.message}).`);
    }
}

export async function main(argv, { fetch = globalThis.fetch, print = console.log } = {}) {
    let args;
    try {
        args = parseArgs({
            args: argv,
            allowPositionals: true,
            options: { rpc: { type: 'string' }, contract: { type: 'string', multiple: true }, help: { type: 'boolean', short: 'h' } },
        });
    } catch (error) {
        print(`${error.message}\n${USAGE}`);
        return EXIT.CANNOT_VERIFY;
    }

    if (args.values.help) {
        print(USAGE);
        return 0;
    }
    if (args.positionals.length !== 2) {
        print(USAGE);
        return EXIT.CANNOT_VERIFY;
    }

    const [filePath, proofPath] = args.positionals;
    try {
        const file = new Uint8Array(await read(filePath));
        const proof = parseProof(await read(proofPath, 'utf8'));
        const networks = JSON.parse(await read(fileURLToPath(new URL('./contracts.json', import.meta.url)), 'utf8'));
        const passphrase = proof.stellar.network_passphrase;
        const network = networks[passphrase] ?? { rpc: null, contracts: [] };
        const url = args.values.rpc ?? network.rpc;
        if (!url) {
            throw new CannotVerify(`No hay un RPC conocido para la red «${passphrase}»: indique uno con --rpc.`);
        }

        print('GovTrace · verificación independiente');
        print(`  Archivo: ${filePath}`);
        print(`  Prueba:  ${proofPath}`);
        print(`  Red:     ${passphrase} (RPC ${url})`);
        print('');

        const result = await verifyEvidence({
            file,
            proof,
            rpc: stellarRpc(url, { fetch }),
            contracts: [...network.contracts, ...(args.values.contract ?? [])],
        });

        for (const step of result.steps) {
            print(`${step.ok ? '✔' : '✘'} ${step.text}`);
        }
        print('');
        print(
            result.authentic
                ? 'AUTÉNTICO: este archivo es exactamente el que se selló en la red Stellar.'
                : 'NO COINCIDE: este archivo no es el que se selló, o la prueba no corresponde a lo que tiene la red.',
        );
        return result.authentic ? EXIT.AUTHENTIC : EXIT.MISMATCH;
    } catch (error) {
        print(`No se pudo verificar: ${error.message}`);
        return EXIT.CANNOT_VERIFY;
    }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    process.exitCode = await main(process.argv.slice(2));
}
