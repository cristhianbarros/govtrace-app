// US-024: el validador público. El navegador calcula el SHA-256 del archivo
// (R-VER-01: el archivo nunca sale del equipo), consigue su prueba de
// inclusión y la comprueba contra la red Stellar con la misma implementación
// del verificador independiente (tools/verify), en todos los contratos de
// GovTrace de esa red (R-MNT-01).
//
// Tres modos:
// - libre: la prueba la da GovTrace, buscada solo por el hash. El resultado
//   es "Auténtico" o "No encontrado", nunca "Alterado";
// - contextual: contra UNA evidencia de la línea de tiempo. Si no es ese
//   archivo, "Alterado o Falso";
// - con prueba adjunta: la que el Verificador descargó (US-026). Solo se
//   consulta la red Stellar, nunca el API de GovTrace (R-INT-04).
import { sha256Hex } from '../../../tools/verify/lib/merkle.mjs';
import { stellarRpc } from '../../../tools/verify/lib/rpc.mjs';
import { CannotVerify, parseProof, verifyEvidence } from '../../../tools/verify/lib/verify.mjs';

export const MAX_BYTES = 10 * 1024 * 1024;

const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];
const ACCEPTED_NAMES = /\.(jpe?g|png|pdf)$/i;

export const MESSAGES = {
    tooLarge: 'El archivo supera el tamaño máximo de 10MB.',
    unsupported: 'Formato no admitido: el validador acepta fotos JPG o PNG y documentos PDF.',
    notFound: '⚠️ Archivo no encontrado. No hay registro de este documento en GovTrace.',
    altered: '❌ Archivo Alterado o Falso. Las huellas criptográficas no coinciden con la blockchain.',
    connection: '⏳ Error de conexión con la red Stellar. No se pudo verificar la inmutabilidad en este momento. Intente más tarde.',
    unpublished: '✅ Archivo Auténtico. (Nota: esta evidencia existe en la blockchain pero la organización aún no la ha publicado).',
    withdrawn: '✅ Archivo Auténtico. (Nota: Esta evidencia fue retirada de la galería pública por la organización, pero su registro criptográfico permanece inalterable).',
    invalidProof: 'La prueba de inclusión no tiene el formato de GovTrace: use el archivo .prueba.json que se descarga junto con la evidencia.',
    govtraceUnavailable: 'No se pudo consultar a GovTrace la prueba de este archivo. Intente más tarde, o verifíquelo con su prueba de inclusión descargada: con ella basta la red Stellar.',
};

// La fecha del sello, en la de Colombia: "2026-09-27".
const sealDate = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Bogota', year: 'numeric', month: '2-digit', day: '2-digit' });

const accepted = (file) => ACCEPTED_TYPES.includes(file.type) || ACCEPTED_NAMES.test(file.name ?? '');

/**
 * @param {{
 *   file: File, mode: 'free'|'contextual'|'attached', reportId?: string, proofFile?: File,
 *   stellar: { rpc_url: string, network_passphrase: string, contracts: string[], explorer_url: ?string },
 *   findProof: (sha256: string, reportId?: string) => Promise<{ visibility: string, proof: object }|null>,
 *   fetch?: typeof fetch,
 * }} input
 * @returns {Promise<{ verdict: 'authentic'|'altered'|'not_found'|'rejected'|'error', message: string, explorerUrl?: ?string, archived?: boolean }>}
 */
export async function validate({ file, mode, reportId, proofFile, stellar, findProof, fetch = globalThis.fetch }) {
    if (!accepted(file)) {
        return { verdict: 'rejected', message: MESSAGES.unsupported };
    }
    if (file.size > MAX_BYTES) {
        return { verdict: 'rejected', message: MESSAGES.tooLarge }; // sin calcular el hash
    }

    const bytes = new Uint8Array(await file.arrayBuffer());
    const denial = mode === 'free' ? { verdict: 'not_found', message: MESSAGES.notFound } : { verdict: 'altered', message: MESSAGES.altered };
    let proof;
    let visibility = 'published';

    if (mode === 'attached') {
        try {
            proof = parseProof(await proofFile.text());
        } catch {
            return { verdict: 'rejected', message: MESSAGES.invalidProof };
        }
    } else {
        let found;
        try {
            found = await findProof(await sha256Hex(bytes), mode === 'contextual' ? reportId : undefined);
        } catch {
            return { verdict: 'error', message: MESSAGES.govtraceUnavailable };
        }
        if (!found) {
            return denial;
        }
        ({ proof, visibility } = found);
    }

    let result;
    try {
        result = await verifyEvidence({ file: bytes, proof, rpc: stellarRpc(stellar.rpc_url, { fetch }), contracts: stellar.contracts });
    } catch (error) {
        // El RPC no respondió o es de otra red: no se pudo verificar, lo que no dice nada del archivo.
        return { verdict: 'error', message: error instanceof CannotVerify && mode === 'attached' ? error.message : MESSAGES.connection };
    }

    if (!result.authentic) {
        return denial;
    }

    const message = { withdrawn: MESSAGES.withdrawn, unpublished: MESSAGES.unpublished }[visibility]
        ?? `✅ Archivo Auténtico e Inmutable. Sellado el ${sealDate.format(new Date(proof.stellar.sealed_at))} en el ledger #${proof.stellar.ledger}.`;

    return {
        verdict: 'authentic',
        message,
        explorerUrl: stellar.explorer_url && proof.stellar.tx_hash ? `${stellar.explorer_url}/tx/${proof.stellar.tx_hash}` : null,
        archived: result.archived,
    };
}
