// Lo mínimo del XDR de Stellar (Stellar-ledger-entries.x, Stellar-contract.x)
// que necesita el verificador: la llave de la entrada donde el contrato de
// sellado de GovTrace guarda un sello, DataKey::Seal(raíz), y la lectura de
// esa entrada. Sin el SDK de Stellar, para no depender de nada.

import { toHex } from './hex.mjs';

const CONTRACT_DATA = 6; // LedgerEntryType
const SC_ADDRESS_CONTRACT = 1; // SCAddressType
const PERSISTENT = 1; // ContractDataDurability
const SCV = { U32: 3, U64: 5, BYTES: 13, SYMBOL: 15, VEC: 16, MAP: 17 }; // SCValType

// Un sello es un mapa de 3 campos: cualquier cosa mucho más grande no es uno.
const MAX_ITEMS = 16;

const ascii = (text) => Uint8Array.from(text, (char) => char.charCodeAt(0));

class Writer {
    #bytes = [];

    uint32(value) {
        this.#bytes.push((value >>> 24) & 0xff, (value >>> 16) & 0xff, (value >>> 8) & 0xff, value & 0xff);
        return this;
    }

    fixed(bytes) {
        this.#bytes.push(...bytes);
        return this;
    }

    // opaque<> y string<>: el largo, los bytes y relleno hasta múltiplo de 4.
    opaque(bytes) {
        this.uint32(bytes.length).fixed(bytes);
        while (this.#bytes.length % 4) {
            this.#bytes.push(0);
        }
        return this;
    }

    toBase64() {
        return btoa(String.fromCharCode(...this.#bytes));
    }
}

class Reader {
    #bytes;
    #view;
    #offset = 0;

    constructor(base64) {
        this.#bytes = Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
        this.#view = new DataView(this.#bytes.buffer);
    }

    uint32() {
        const value = this.#view.getUint32(this.#offset);
        this.#offset += 4;
        return value;
    }

    uint64() {
        const value = this.#view.getBigUint64(this.#offset);
        this.#offset += 8;
        return value;
    }

    fixed(length) {
        if (this.#offset + length > this.#bytes.length) {
            throw new Error('El XDR de la entrada está incompleto.');
        }
        const bytes = this.#bytes.slice(this.#offset, this.#offset + length);
        this.#offset += length;
        return bytes;
    }

    opaque() {
        const bytes = this.fixed(this.uint32());
        this.#offset += (4 - (bytes.length % 4)) % 4;
        return bytes;
    }

    // T* en XDR: un bool y, si está, el valor.
    optional(read) {
        return this.uint32() ? read() : null;
    }

    array(read) {
        const length = this.uint32();
        if (length > MAX_ITEMS) {
            throw new Error(`El XDR de la entrada no es un sello: trae ${length} elementos.`);
        }
        return Array.from({ length }, read);
    }

    get finished() {
        return this.#offset === this.#bytes.length;
    }
}

// Los valores que puede traer un sello: {type, value}, para no confundir un tipo con otro.
function readScVal(reader) {
    const type = reader.uint32();
    switch (type) {
        case SCV.U32:
            return { type, value: reader.uint32() };
        case SCV.U64:
            return { type, value: reader.uint64() };
        case SCV.BYTES:
            return { type, value: reader.opaque() };
        case SCV.SYMBOL:
            return { type, value: String.fromCharCode(...reader.opaque()) };
        case SCV.VEC:
            return { type, value: reader.optional(() => reader.array(() => readScVal(reader))) ?? [] };
        case SCV.MAP:
            return { type, value: reader.optional(() => reader.array(() => [readScVal(reader), readScVal(reader)])) ?? [] };
        default:
            throw new Error(`El XDR de la entrada no es un sello: trae un valor de tipo ${type}.`);
    }
}

function expectType(scVal, type, what) {
    if (scVal?.type !== type) {
        throw new Error(`El XDR de la entrada no es un sello: ${what} no tiene el tipo esperado.`);
    }
    return scVal.value;
}

/** The base64 LedgerKey of DataKey::Seal(root) in the contract: what getLedgerEntries is asked for. */
export function sealLedgerKey(contract, root) {
    return new Writer()
        .uint32(CONTRACT_DATA)
        .uint32(SC_ADDRESS_CONTRACT)
        .fixed(contract)
        .uint32(SCV.VEC)
        .uint32(1) // SCVec* presente
        .uint32(2)
        .uint32(SCV.SYMBOL)
        .opaque(ascii('Seal'))
        .uint32(SCV.BYTES)
        .opaque(root)
        .uint32(PERSISTENT)
        .toBase64();
}

/**
 * Reads the LedgerEntryData that getLedgerEntries returns for a seal: the
 * contract and root it belongs to, and the seal — the ledger that closed
 * it, that ledger's time (seconds) and the worksite reference.
 */
export function readSeal(entryBase64) {
    const reader = new Reader(entryBase64);

    if (reader.uint32() !== CONTRACT_DATA || reader.uint32() !== 0 /* ExtensionPoint */ || reader.uint32() !== SC_ADDRESS_CONTRACT) {
        throw new Error('El XDR no es una entrada de datos de un contrato.');
    }
    const contract = reader.fixed(32);

    const [name, root] = expectType(readScVal(reader), SCV.VEC, 'la llave');
    if (expectType(name, SCV.SYMBOL, 'la llave') !== 'Seal' || reader.uint32() !== PERSISTENT) {
        throw new Error('El XDR no es la entrada de un sello.');
    }

    const fields = Object.fromEntries(
        expectType(readScVal(reader), SCV.MAP, 'el sello').map(([key, value]) => [expectType(key, SCV.SYMBOL, 'un campo'), value]),
    );

    if (!reader.finished) {
        throw new Error('El XDR de la entrada trae más de lo que tiene un sello.');
    }

    return {
        contract: toHex(contract),
        root: toHex(expectType(root, SCV.BYTES, 'la raíz')),
        ledger: expectType(fields.ledger, SCV.U32, 'el ledger'),
        sealedAt: Number(expectType(fields.sealed_at, SCV.U64, 'la hora')),
        worksite: toHex(expectType(fields.worksite, SCV.BYTES, 'la obra')),
    };
}
