// @vitest-environment node
//
// Iteración 23 — US-026: el verificador independiente (tools/verify), sin red.
// El RPC repite lo que respondió la red local standalone al sellar
// tests/fixtures/evidence/foto.jpg (tests/fixtures/verify/sealed.json); el
// XDR se compara byte a byte con el que arma el SDK del servidor. Contra la
// red de verdad, con una evidencia publicada por GovTrace: make verify-check.

import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { fromHex } from '../lib/hex.mjs';
import { decodeContractId } from '../lib/strkey.mjs';
import { CannotVerify, parseProof, verifyEvidence } from '../lib/verify.mjs';
import { readSeal, sealLedgerKey } from '../lib/xdr.mjs';
import { EXIT, main } from '../verify.mjs';

const fixture = JSON.parse(readFileSync(new URL('../../../tests/fixtures/verify/sealed.json', import.meta.url), 'utf8'));
const photo = new Uint8Array(readFileSync(new URL('../../../tests/fixtures/evidence/foto.jpg', import.meta.url)));
const networks = JSON.parse(readFileSync(new URL('../contracts.json', import.meta.url), 'utf8'));
const CONTRACT = fixture.proof.stellar.contract_id;
const ENTRY = fixture.rpc.getLedgerEntries.entries[0];

/**
 * A Stellar RPC that answers what the local network answered — or what a
 * test changes. Like a real one, getLedgerEntries only returns entries of
 * the keys it was asked for.
 */
function replayRpc(change = (answers) => answers) {
    const answers = change(structuredClone(fixture.rpc));
    const calls = [];
    return {
        calls,
        call: async (method, params) => {
            calls.push({ method, params });
            if (method === 'getLedgerEntries') {
                return { ...answers.getLedgerEntries, entries: answers.getLedgerEntries.entries.filter((entry) => params.keys.includes(entry.key)) };
            }
            return answers[method];
        },
    };
}

/** A later version of the contract, on the same network: it never sealed this root. */
const NEWER_CONTRACT = 'CALFTQY2X3YTEHFZORY7FQJUPXB2BXEGBCCHQVWTKB3WAVA65QHMTSFA';

/** The real seal entry, as if another contract kept it, closed in another ledger. */
function entryIn(contract, ledger) {
    const bytes = Uint8Array.from(atob(ENTRY.xdr), (char) => char.charCodeAt(0));
    bytes.set(decodeContractId(contract), 12); // tras LedgerEntryType, ExtensionPoint y SCAddressType
    const ledgerValue = Buffer.from(bytes).indexOf(Buffer.from('ledger\0\0\0\0\0\x03', 'latin1')) + 12;
    new DataView(bytes.buffer).setUint32(ledgerValue, ledger);

    return {
        key: sealLedgerKey(decodeContractId(contract), fromHex(fixture.proof.merkle_root, 32)),
        xdr: btoa(String.fromCharCode(...bytes)),
        liveUntilLedgerSeq: ENTRY.liveUntilLedgerSeq,
    };
}

const proof = (change = (value) => value) => change(structuredClone(fixture.proof));

const verify = (overrides = {}) => verifyEvidence({ file: photo, proof: proof(), rpc: replayRpc(), contracts: [CONTRACT], ...overrides });

describe('las direcciones de contrato (StrKey)', () => {
    it('decodes a contract address to the 32 bytes its ledger entry names', () => {
        expect(Buffer.from(decodeContractId(CONTRACT)).toString('hex')).toBe(readSeal(ENTRY.xdr).contract);
    });

    it('refuses an address with a character changed: its checksum no longer matches', () => {
        const changed = CONTRACT.slice(0, 10) + (CONTRACT[10] === 'A' ? 'B' : 'A') + CONTRACT.slice(11);

        expect(() => decodeContractId(changed)).toThrow('suma de control');
    });

    it('refuses an account address (G…): a seal lives in a contract', () => {
        expect(() => decodeContractId('GAIH3ULLFQ4DGSECF2AR555KZ4KNDGEKN4AFI4SU2M7B43MGK3QJZNSR')).toThrow('No es la dirección de un contrato');
    });

    it('lists only valid contract addresses as GovTrace contracts', () => {
        const listed = Object.entries(networks).filter(([name]) => name !== '_comment').flatMap(([, network]) => network.contracts);

        expect(listed.length).toBeGreaterThan(0);
        listed.forEach((address) => expect(decodeContractId(address)).toHaveLength(32));
    });
});

describe('el XDR de un sello', () => {
    it('asks for the same ledger key, byte for byte, as the Stellar SDK of the server', () => {
        const key = sealLedgerKey(decodeContractId(CONTRACT), fromHex(fixture.proof.merkle_root, 32));

        expect(key).toBe(fixture.ledger_key_xdr);
        expect(key).toBe(ENTRY.key);
    });

    it('reads the seal the network keeps: its root, ledger, ledger time and worksite', () => {
        expect(readSeal(ENTRY.xdr)).toEqual({
            contract: Buffer.from(decodeContractId(CONTRACT)).toString('hex'),
            root: fixture.proof.merkle_root,
            ledger: fixture.proof.stellar.ledger,
            sealedAt: Date.parse(fixture.proof.stellar.sealed_at) / 1000,
            worksite: fixture.proof.worksite_reference,
        });
    });

    it('refuses an entry that is not a seal', () => {
        expect(() => readSeal(ENTRY.key)).toThrow();
        expect(() => readSeal(ENTRY.xdr.slice(0, 40))).toThrow();
    });
});

describe('la verificación', () => {
    it('Verificación de un archivo auténtico: hashes the file, rebuilds the root and finds it in the Smart Contract', async () => {
        const rpc = replayRpc();

        const result = await verify({ rpc });

        expect(result).toMatchObject({ authentic: true, archived: false });
        expect(result.steps.map((step) => step.ok)).toEqual([true, true, true]);
        expect(rpc.calls.map((call) => call.method)).toEqual(['getNetwork', 'getLedgerEntries']);
        expect(rpc.calls[1].params).toEqual({ keys: [fixture.ledger_key_xdr] });
    });

    it.each([
        ['vencida hace poco', (answers) => ((answers.getLedgerEntries.entries[0].liveUntilLedgerSeq = answers.getLedgerEntries.latestLedger - 1), answers)],
        ['vigencia 0, como la da el RPC', (answers) => ((answers.getLedgerEntries.entries[0].liveUntilLedgerSeq = 0), answers)],
    ])('Sello archivado por la red (%s): reads it without restoring it or paying fees, and says it is archived', async (_, archive) => {
        const rpc = replayRpc(archive);

        const result = await verify({ rpc });

        expect(result).toMatchObject({ authentic: true, archived: true });
        expect(result.steps.at(-1).text).toContain('archivado por su vigencia');
        // Solo lecturas: ni simulateTransaction ni sendTransaction, así que nada que firmar ni pagar.
        expect(rpc.calls.map((call) => call.method)).toEqual(['getNetwork', 'getLedgerEntries']);
    });

    it('Verificación de un archivo alterado: a copy with a single byte changed does not match the seal', async () => {
        const altered = photo.slice();
        altered[altered.length - 1] ^= 0x01;

        const result = await verify({ file: altered });

        expect(result.authentic).toBe(false);
        expect(result.steps).toHaveLength(1);
        expect(result.steps[0].text).toContain('El archivo no es el de la prueba');
    });

    it('does not match a proof edited to fit another file: the root no longer adds up', async () => {
        const other = new TextEncoder().encode('otra foto');
        const otherSha = Buffer.from(await crypto.subtle.digest('SHA-256', other)).toString('hex');

        const result = await verify({ file: other, proof: proof((p) => ((p.file.sha256 = otherSha), (p.leaves[0] = otherSha), p)) });

        expect(result.authentic).toBe(false);
        expect(result.steps.at(-1).text).toContain('no forman su raíz');
    });

    it('does not match a proof whose Merkle path was changed, even if its leaves add up', async () => {
        const result = await verify({ proof: proof((p) => ((p.proof = [fixture.proof.merkle_root]), p)) });

        expect(result.authentic).toBe(false);
        expect(result.steps.at(-1).text).toContain('El camino de Merkle de la prueba no lleva del archivo a su raíz');
    });

    it('Sello de una versión anterior del Smart Contract: asks every historical address, and finds it in version 1', async () => {
        const rpc = replayRpc();

        const result = await verify({ rpc, contracts: [NEWER_CONTRACT, CONTRACT] });

        expect(result.authentic).toBe(true);
        expect(rpc.calls[1].params.keys).toEqual([sealLedgerKey(decodeContractId(NEWER_CONTRACT), fromHex(fixture.proof.merkle_root, 32)), fixture.ledger_key_xdr]);
        expect(result.steps.at(-1).text).toContain(`en el contrato ${CONTRACT}`);
    });

    it('finds the seal even if the proof names another contract: the contract of the proof is not taken on trust', async () => {
        const result = await verify({ proof: proof((p) => ((p.stellar.contract_id = NEWER_CONTRACT), p)) });

        expect(result.authentic).toBe(true);
        expect(result.steps.at(-1).text).toContain(`la prueba nombra ${NEWER_CONTRACT}`);
    });

    it.each([
        ['la nombra la prueba', CONTRACT],
        ['la prueba nombra el otro', NEWER_CONTRACT],
    ])('takes, of a root in two GovTrace contracts, the seal that is the one of the proof (%s)', async (_, named) => {
        const rpc = replayRpc((answers) => ((answers.getLedgerEntries.entries = [entryIn(NEWER_CONTRACT, 1), ENTRY]), answers));

        const result = await verify({ rpc, contracts: [NEWER_CONTRACT, CONTRACT], proof: proof((p) => ((p.stellar.contract_id = named), p)) });

        expect(result.authentic).toBe(true);
        expect(readSeal(entryIn(NEWER_CONTRACT, 1).xdr)).toMatchObject({ ledger: 1, root: fixture.proof.merkle_root });
    });

    it('does not match a root sealed only in a contract that is not GovTrace: anyone can deploy a look-alike', async () => {
        const result = await verify({ contracts: networks['Test SDF Network ; September 2015'].contracts });

        expect(result.authentic).toBe(false);
        expect(result.steps.at(-1).text).toContain('en ninguno de los contratos de GovTrace');
        expect(result.steps.at(-1).text).toContain(`La prueba nombra el contrato ${CONTRACT}, que no es uno de ellos`);
    });

    it('does not match when it knows no GovTrace contract on that network, like the local one', async () => {
        const rpc = replayRpc();

        const result = await verify({ rpc, contracts: [] });

        expect(result.authentic).toBe(false);
        expect(result.steps.at(-1).text).toContain('No hay contratos de GovTrace conocidos para la red');
        expect(rpc.calls.map((call) => call.method)).not.toContain('getLedgerEntries');
    });

    it('does not match a root the contract never sealed', async () => {
        const result = await verify({ rpc: replayRpc((answers) => ((answers.getLedgerEntries.entries = []), answers)) });

        expect(result.authentic).toBe(false);
        expect(result.steps.at(-1).text).toContain('La red no tiene la raíz');
    });

    it.each([
        ['el ledger', (p) => (p.stellar.ledger -= 1)],
        ['la hora', (p) => (p.stellar.sealed_at = new Date(Date.parse(p.stellar.sealed_at) - 60_000).toISOString())],
        ['la obra', (p) => (p.worksite_reference = '0'.repeat(64))],
    ])('does not match a proof that lies about when or for what it was sealed (%s)', async (_, lie) => {
        const result = await verify({ proof: proof((p) => (lie(p), p)) });

        expect(result.authentic).toBe(false);
        expect(result.steps.at(-1).text).toContain(`La red selló la raíz en el ledger ${fixture.proof.stellar.ledger}`);
    });

    it('cannot verify with an RPC of another network', async () => {
        const rpc = replayRpc((answers) => ((answers.getNetwork.passphrase = 'Test SDF Network ; September 2015'), answers));

        await expect(verify({ rpc })).rejects.toThrow(CannotVerify);
    });

    it('cannot verify with an RPC that answers the entry of another seal', async () => {
        const rpc = replayRpc((answers) => ((answers.getLedgerEntries.entries[0].xdr = readFileSync(new URL('../../../tests/fixtures/verify/other-seal.xdr', import.meta.url), 'utf8').trim()), answers));

        await expect(verify({ rpc })).rejects.toThrow('la entrada de otro sello');
    });

    it('refuses a file that is not a GovTrace proof', () => {
        expect(() => parseProof('{"format": "otra-cosa/1"}')).toThrow(CannotVerify);
        expect(() => parseProof('no es json')).toThrow('no es un JSON válido');
    });
});

describe('la línea de comandos', () => {
    const dir = mkdtempSync(join(tmpdir(), 'govtrace-verify-'));
    const photoPath = join(dir, fixture.proof.file.name);
    const proofPath = join(dir, 'evidencia.prueba.json');
    writeFileSync(photoPath, photo);
    writeFileSync(proofPath, JSON.stringify(fixture.proof));

    /** fetch against a replayed RPC: what the local network answered. */
    const fetch = async (_, init) => ({ ok: true, status: 200, json: async () => ({ jsonrpc: '2.0', id: 1, result: fixture.rpc[JSON.parse(init.body).method] }) });

    async function run(argv) {
        const lines = [];
        const code = await main(argv, { fetch, print: (line) => lines.push(line) });
        return { code, output: lines.join('\n') };
    }

    it('says AUTÉNTICO and exits 0 for the sealed file', async () => {
        const { code, output } = await run([photoPath, proofPath, '--rpc', 'http://stellar:8000/rpc', '--contract', CONTRACT]);

        expect(code).toBe(EXIT.AUTHENTIC);
        expect(output).toContain('AUTÉNTICO');
    });

    it('says NO COINCIDE and exits 1 for an altered copy', async () => {
        const altered = join(dir, 'alterada.jpg');
        writeFileSync(altered, Buffer.concat([photo, Buffer.from('x')]));

        const { code, output } = await run([altered, proofPath, '--rpc', 'http://stellar:8000/rpc', '--contract', CONTRACT]);

        expect(code).toBe(EXIT.MISMATCH);
        expect(output).toContain('NO COINCIDE');
    });

    it('El script no depende de GovTrace: its only requests go to the Stellar RPC it is given', async () => {
        const urls = [];
        const code = await main([photoPath, proofPath, '--rpc', 'http://stellar:8000/rpc', '--contract', CONTRACT], {
            fetch: async (url, init) => (urls.push(url), fetch(url, init)),
            print: () => {},
        });

        expect(code).toBe(EXIT.AUTHENTIC);
        expect(new Set(urls)).toEqual(new Set(['http://stellar:8000/rpc']));
    });

    it('does not take the contract of the proof on trust: without --contract, a local seal does not match', async () => {
        const { code } = await run([photoPath, proofPath, '--rpc', 'http://stellar:8000/rpc']);

        expect(code).toBe(EXIT.MISMATCH);
    });

    it('exits 2 when it cannot verify: no RPC known for the network, or a missing file', async () => {
        expect((await run([photoPath, proofPath])).output).toContain('indique uno con --rpc');
        expect((await run([photoPath, proofPath])).code).toBe(EXIT.CANNOT_VERIFY);
        expect((await run([join(dir, 'no-existe.jpg'), proofPath, '--rpc', 'http://x'])).code).toBe(EXIT.CANNOT_VERIFY);
        expect((await run([photoPath])).code).toBe(EXIT.CANNOT_VERIFY);
    });
});
