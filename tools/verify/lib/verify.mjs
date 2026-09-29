// US-026 / R-VER-01: la verificación de una evidencia descargada de GovTrace,
// contra su prueba de inclusión y la red Stellar, sin pasar por GovTrace.

import { fromHex, toHex } from './hex.mjs';
import { merkleRoot, sha256Hex, verifyProof } from './merkle.mjs';
import { decodeContractId } from './strkey.mjs';
import { readSeal, sealLedgerKey } from './xdr.mjs';

export const PROOF_FORMAT = 'govtrace-proof/1';

/** Something kept the verification from being done: it says nothing about the file. */
export class CannotVerify extends Error {}

const isSha256 = (value) => typeof value === 'string' && /^[0-9a-f]{64}$/.test(value);

const utc = (seconds) => new Date(seconds * 1000).toISOString().replace('.000Z', 'Z');

/** The proof, once it has the shape GovTrace gives it (the one EvidenceDownloadTest pins). */
export function parseProof(text) {
    let proof;
    try {
        proof = JSON.parse(text);
    } catch {
        throw new CannotVerify('La prueba no es un JSON válido.');
    }

    const wellFormed =
        proof?.format === PROOF_FORMAT &&
        isSha256(proof.file?.sha256) &&
        Array.isArray(proof.leaves) &&
        proof.leaves.length > 0 &&
        proof.leaves.every(isSha256) &&
        Number.isInteger(proof.leaf_index) &&
        Array.isArray(proof.proof) &&
        proof.proof.every(isSha256) &&
        isSha256(proof.merkle_root) &&
        isSha256(proof.worksite_reference) &&
        typeof proof.stellar?.network_passphrase === 'string' &&
        (proof.stellar?.contract_id == null || typeof proof.stellar.contract_id === 'string') && // solo informativo
        Number.isInteger(proof.stellar?.ledger) &&
        !Number.isNaN(Date.parse(proof.stellar?.sealed_at));

    if (!wellFormed) {
        throw new CannotVerify(`La prueba no tiene el formato ${PROOF_FORMAT} de GovTrace.`);
    }
    return proof;
}

/**
 * Three checks, in order; the first that fails is the answer:
 * 1. the file has the SHA-256 of the proof;
 * 2. the proof's leaves and Merkle path lead from it to the root;
 * 3. the network has that root sealed in one of GovTrace's contracts on it
 *    — all of them, earlier versions too (R-MNT-01) — in the ledger and at
 *    the time the proof says. Never in the contract the proof names just
 *    because it names it: anyone can deploy a look-alike contract and seal
 *    whatever they want in it. Read as a ledger entry, it works just the
 *    same if the seal is archived by its TTL, and needs no account.
 *
 * @param {{ file: Uint8Array, proof: object, rpc: { call(method: string, params: object): Promise<any> }, contracts: string[] }} input
 * @returns {Promise<{ authentic: boolean, archived: boolean, steps: { ok: boolean, text: string }[] }>}
 */
export async function verifyEvidence({ file, proof, rpc, contracts }) {
    const steps = [];
    const passed = (text) => steps.push({ ok: true, text });
    const failed = (text) => ({ authentic: false, archived: false, steps: [...steps, { ok: false, text }] });

    const sha256 = await sha256Hex(file);
    if (sha256 !== proof.file.sha256) {
        return failed(`El archivo no es el de la prueba: su SHA-256 es ${sha256}, y la prueba es de ${proof.file.sha256}.`);
    }
    passed(`El archivo tiene el SHA-256 de la prueba: ${sha256}.`);

    if (proof.leaves[proof.leaf_index] !== sha256 || (await merkleRoot(proof.leaves)) !== proof.merkle_root) {
        return failed('Las hojas de la prueba no forman su raíz con el archivo en su lugar.');
    }
    if (!(await verifyProof(sha256, proof.proof, proof.merkle_root))) {
        return failed('El camino de Merkle de la prueba no lleva del archivo a su raíz.');
    }
    passed(`Es la hoja ${proof.leaf_index + 1} de ${proof.leaves.length} de su árbol, y su camino de Merkle lleva a la raíz ${proof.merkle_root}.`);

    const { network_passphrase: passphrase, contract_id: named } = proof.stellar;
    const network = await rpc.call('getNetwork', {});
    if (network?.passphrase !== passphrase) {
        throw new CannotVerify(`El RPC es de la red «${network?.passphrase}», y la prueba es de «${passphrase}».`);
    }

    const notOurs = named && !contracts.includes(named)
        ? ` La prueba nombra el contrato ${named}, que no es uno de ellos: cualquiera puede desplegar uno parecido. Si la prueba es de otra instalación de GovTrace, indique su contrato con --contract.`
        : '';
    if (contracts.length === 0) {
        return failed(`No hay contratos de GovTrace conocidos para la red «${passphrase}» (contracts.json).${notOurs}`);
    }

    // R-MNT-01: todos los contratos de GovTrace en esa red, también los anteriores, en una sola consulta.
    const byHash = new Map();
    for (const contract of contracts) {
        try {
            byHash.set(toHex(decodeContractId(contract)), contract);
        } catch (error) {
            throw new CannotVerify(error.message);
        }
    }
    const root = fromHex(proof.merkle_root, 32);
    const answer = await rpc.call('getLedgerEntries', { keys: [...byHash.keys()].map((contract) => sealLedgerKey(fromHex(contract, 32), root)) });

    const found = (answer?.entries ?? []).map((entry) => ({ entry, seal: readSeal(entry.xdr) }));
    if (found.some(({ seal }) => !byHash.has(seal.contract) || seal.root !== proof.merkle_root)) {
        throw new CannotVerify('El RPC respondió con la entrada de otro sello.');
    }
    if (found.length === 0) {
        return failed(`La red no tiene la raíz ${proof.merkle_root} sellada en ninguno de los contratos de GovTrace en esa red (${contracts.length}).${notOurs}`);
    }

    // Si la raíz estuviera en más de uno, el sello que es el de la prueba: en su ledger, a su hora y de su obra.
    const sealedAt = Date.parse(proof.stellar.sealed_at) / 1000;
    const isTheProofs = ({ seal }) => seal.ledger === proof.stellar.ledger && seal.sealedAt === sealedAt && seal.worksite === proof.worksite_reference;
    const { entry, seal } = found.find(isTheProofs) ?? found[0];
    const contract = byHash.get(seal.contract);

    if (!isTheProofs({ seal })) {
        return failed(
            `La red selló la raíz en el ledger ${seal.ledger}, cerrado el ${utc(seal.sealedAt)}, para la obra ${seal.worksite}; ` +
                `la prueba dice ledger ${proof.stellar.ledger}, ${utc(sealedAt)}, obra ${proof.worksite_reference}.`,
        );
    }
    const alsoNamed = named && named !== contract ? ` (la prueba nombra ${named})` : '';
    passed(`La red Stellar tiene la raíz sellada en el contrato ${contract}${alsoNamed}: ledger ${seal.ledger}, cerrado el ${utc(seal.sealedAt)}.`);

    // Archivado: pasó su vigencia (TTL) y la red lo sacó del estado vivo, con sus datos intactos.
    const archived = typeof entry.liveUntilLedgerSeq === 'number' && entry.liveUntilLedgerSeq < answer.latestLedger;
    if (archived) {
        passed('El sello está archivado por su vigencia en la red (TTL): sus datos siguen ahí y la verificación vale igual. Para que un contrato lo vuelva a usar, habría que restaurarlo.');
    }

    return { authentic: true, archived, steps };
}
