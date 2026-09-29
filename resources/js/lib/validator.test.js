// @vitest-environment node
//
// Iteración 27 — US-024: el validador público. El navegador calcula el
// SHA-256 del archivo (R-VER-01), consigue su prueba de inclusión — de
// GovTrace, solo por el hash, o la que aporta el Verificador — y la
// comprueba contra la red Stellar con la misma implementación del
// verificador independiente (tools/verify). El RPC repite lo que respondió
// la red local al sellar tests/fixtures/evidence/foto.jpg (it. 23).
import { readFileSync } from 'node:fs';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { MESSAGES, validate } from './validator.js';

const fixture = JSON.parse(readFileSync(new URL('../../../tests/fixtures/verify/sealed.json', import.meta.url), 'utf8'));
const photo = new Uint8Array(readFileSync(new URL('../../../tests/fixtures/evidence/foto.jpg', import.meta.url)));
const TX = fixture.proof.stellar.tx_hash;
const NEWER_CONTRACT = 'CALFTQY2X3YTEHFZORY7FQJUPXB2BXEGBCCHQVWTKB3WAVA65QHMTSFA';

const stellar = {
    rpc_url: 'https://rpc.example.org',
    network_passphrase: fixture.proof.stellar.network_passphrase,
    contracts: [fixture.proof.stellar.contract_id],
    explorer_url: 'https://stellar.expert/explorer/testnet',
};

const file = (bytes = photo, name = 'obra-gaira.jpg', type = 'image/jpeg') => new File([bytes], name, { type });
const altered = () => file(Uint8Array.from([...photo, 0x01]));

/** A Stellar RPC that answers what the local network answered; like a real one, only the entries it was asked for. */
function network(change = (answers) => answers) {
    const answers = change(structuredClone(fixture.rpc));
    return vi.fn(async (url, init) => {
        const { method, params } = JSON.parse(init.body);
        const result = method === 'getLedgerEntries'
            ? { ...answers.getLedgerEntries, entries: answers.getLedgerEntries.entries.filter((entry) => params.keys.includes(entry.key)) }
            : answers[method];
        return { ok: true, status: 200, json: async () => ({ jsonrpc: '2.0', id: 1, result }) };
    });
}

/** GovTrace's answer to "the proof of this hash": what GET /public/proofs/{sha256} gives, or null (404). */
const govtrace = (answer = { report_id: 12, visibility: 'published', proof: fixture.proof }) => vi.fn(async () => answer);

const AUTHENTIC = { verdict: 'authentic', message: '✅ Archivo Auténtico e Inmutable. Sellado el 2026-09-28 en el ledger #38373.', explorerUrl: `https://stellar.expert/explorer/testnet/tx/${TX}` };

afterEach(() => vi.restoreAllMocks());

describe('el validador público (US-024)', () => {
    it('Modo libre con un archivo auténtico: the hash in the browser, the root against the contract, the green banner', async () => {
        const findProof = govtrace();
        const fetch = network();

        const result = await validate({ file: file(), mode: 'free', stellar, findProof, fetch });

        // Solo el hash sale del navegador; el archivo, nunca.
        expect(findProof).toHaveBeenCalledWith(fixture.proof.file.sha256, undefined);
        expect(fetch.mock.calls.map(([url]) => url)).toEqual(['https://rpc.example.org', 'https://rpc.example.org']);
        expect(result).toMatchObject(AUTHENTIC);
    });

    it('Modo contextual con un archivo alterado: the red banner', async () => {
        const findProof = govtrace(null);

        const result = await validate({ file: altered(), mode: 'contextual', reportId: 12, stellar, findProof, fetch: network() });

        expect(findProof).toHaveBeenCalledWith(expect.any(String), 12);
        expect(result).toEqual({ verdict: 'altered', message: '❌ Archivo Alterado o Falso. Las huellas criptográficas no coinciden con la blockchain.' });
    });

    it('El modo libre nunca dice "Alterado": an altered copy is not found', async () => {
        const result = await validate({ file: altered(), mode: 'free', stellar, findProof: govtrace(null), fetch: network() });

        expect(result).toEqual({ verdict: 'not_found', message: '⚠️ Archivo no encontrado. No hay registro de este documento en GovTrace.' });
    });

    it('Modo con prueba adjunta, sin consultar a GovTrace: only the Stellar network is asked', async () => {
        const findProof = govtrace();
        const fetch = network();
        const proofFile = new File([JSON.stringify(fixture.proof)], 'evidencia.prueba.json', { type: 'application/json' });

        const result = await validate({ file: file(), mode: 'attached', proofFile, stellar, findProof, fetch });

        expect(findProof).not.toHaveBeenCalled();
        expect(fetch.mock.calls.every(([url]) => url === 'https://rpc.example.org')).toBe(true);
        expect(result).toMatchObject(AUTHENTIC);
    });

    it.each([
        ['foto.jpg', 'image/jpeg', true],
        ['foto.png', 'image/png', true],
        ['acta.pdf', 'application/pdf', true],
        ['video.mp4', 'video/mp4', false],
    ])('Formatos aceptados: %s', async (name, type, processed) => {
        const findProof = govtrace(null);

        const result = await validate({ file: file(photo, name, type), mode: 'free', stellar, findProof, fetch: network() });

        expect(findProof).toHaveBeenCalledTimes(processed ? 1 : 0);
        expect(result.verdict === 'rejected').toBe(!processed);
        if (!processed) {
            expect(result.message).toBe(MESSAGES.unsupported);
        }
    });

    it('Archivo de más de 10 MB: the browser does not compute its hash', async () => {
        const digest = vi.spyOn(crypto.subtle, 'digest');

        const result = await validate({ file: file(new Uint8Array(12 * 1024 * 1024)), mode: 'free', stellar, findProof: govtrace(), fetch: network() });

        expect(digest).not.toHaveBeenCalled();
        expect(result).toEqual({ verdict: 'rejected', message: 'El archivo supera el tamaño máximo de 10MB.' });
    });

    it('Falla de conexión con Stellar', async () => {
        const fetch = vi.fn(async () => {
            throw new TypeError('Failed to fetch');
        });

        const result = await validate({ file: file(), mode: 'free', stellar, findProof: govtrace(), fetch });

        expect(result).toEqual({ verdict: 'error', message: '⏳ Error de conexión con la red Stellar. No se pudo verificar la inmutabilidad en este momento. Intente más tarde.' });
    });

    it('Sellos de una versión anterior del Smart Contract siguen verificables', async () => {
        const result = await validate({ file: file(), mode: 'free', stellar: { ...stellar, contracts: [NEWER_CONTRACT, fixture.proof.stellar.contract_id] }, findProof: govtrace(), fetch: network() });

        expect(result).toMatchObject(AUTHENTIC);
    });

    it('Evidencia sellada que la organización no ha publicado', async () => {
        const result = await validate({ file: file(), mode: 'free', stellar, findProof: govtrace({ report_id: 12, visibility: 'unpublished', proof: fixture.proof }), fetch: network() });

        expect(result).toMatchObject({ verdict: 'authentic', message: '✅ Archivo Auténtico. (Nota: esta evidencia existe en la blockchain pero la organización aún no la ha publicado).' });
    });

    it('Copia de una evidencia retirada después de descargarla', async () => {
        const result = await validate({ file: file(), mode: 'free', stellar, findProof: govtrace({ report_id: 12, visibility: 'withdrawn', proof: fixture.proof }), fetch: network() });

        expect(result).toMatchObject({ verdict: 'authentic', message: '✅ Archivo Auténtico. (Nota: Esta evidencia fue retirada de la galería pública por la organización, pero su registro criptográfico permanece inalterable).' });
    });
});

describe('reglas derivadas del validador', () => {
    it('does not take GovTrace at its word: a proof the network does not confirm is not found, in the free mode', async () => {
        const fetch = network((answers) => ((answers.getLedgerEntries.entries = []), answers));

        expect((await validate({ file: file(), mode: 'free', stellar, findProof: govtrace(), fetch })).verdict).toBe('not_found');
    });

    it('says altered in the contextual and attached modes when the network does not confirm it', async () => {
        const fetch = network((answers) => ((answers.getLedgerEntries.entries = []), answers));
        const proofFile = new File([JSON.stringify(fixture.proof)], 'evidencia.prueba.json');

        expect((await validate({ file: file(), mode: 'contextual', reportId: 12, stellar, findProof: govtrace(), fetch })).verdict).toBe('altered');
        expect((await validate({ file: altered(), mode: 'attached', proofFile, stellar, findProof: govtrace(), fetch: network() })).verdict).toBe('altered');
    });

    it('refuses a proof that is not a GovTrace proof', async () => {
        const proofFile = new File(['{"format": "otra-cosa"}'], 'prueba.json');

        const result = await validate({ file: file(), mode: 'attached', proofFile, stellar, findProof: govtrace(), fetch: network() });

        expect(result).toEqual({ verdict: 'rejected', message: MESSAGES.invalidProof });
    });

    it('suggests the downloaded proof when GovTrace does not answer: the network alone is enough with it', async () => {
        const findProof = vi.fn(async () => {
            throw new Error('Network Error');
        });

        const result = await validate({ file: file(), mode: 'free', stellar, findProof, fetch: network() });

        expect(result).toEqual({ verdict: 'error', message: MESSAGES.govtraceUnavailable });
    });

    it('says so when the seal was archived by its TTL, and still authentic', async () => {
        const fetch = network((answers) => ((answers.getLedgerEntries.entries[0].liveUntilLedgerSeq = 0), answers));

        const result = await validate({ file: file(), mode: 'free', stellar, findProof: govtrace(), fetch });

        expect(result).toMatchObject({ ...AUTHENTIC, archived: true });
    });
});
