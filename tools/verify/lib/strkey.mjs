// Las direcciones de Stellar (StrKey, SEP-23): en base32, un byte de versión,
// la llave de 32 bytes y un CRC16-XModem. Solo lo que usa el verificador: la
// dirección de un contrato (C…).

const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
const CONTRACT_VERSION = 2 << 3; // la "C"

function crc16xmodem(bytes) {
    let crc = 0;
    for (const byte of bytes) {
        crc ^= byte << 8;
        for (let bit = 0; bit < 8; bit++) {
            crc = crc & 0x8000 ? ((crc << 1) ^ 0x1021) & 0xffff : (crc << 1) & 0xffff;
        }
    }
    return crc;
}

// 56 caracteres de 5 bits: exactamente 35 bytes.
function base32Decode(text) {
    const bytes = [];
    let buffer = 0;
    let bits = 0;
    for (const char of text) {
        buffer = ((buffer << 5) | ALPHABET.indexOf(char)) & 0xffff;
        bits += 5;
        if (bits >= 8) {
            bits -= 8;
            bytes.push((buffer >> bits) & 0xff);
        }
    }
    return Uint8Array.from(bytes);
}

/** The 32 bytes of a contract address (C…), once its version and checksum are right. */
export function decodeContractId(address) {
    if (typeof address !== 'string' || !/^C[A-Z2-7]{55}$/.test(address)) {
        throw new Error(`No es la dirección de un contrato de Stellar: ${address}`);
    }

    const bytes = base32Decode(address);
    const checksum = bytes[33] | (bytes[34] << 8); // little-endian
    if (bytes[0] !== CONTRACT_VERSION || crc16xmodem(bytes.subarray(0, 33)) !== checksum) {
        throw new Error(`La dirección de contrato ${address} no es válida: su suma de control no coincide.`);
    }

    return bytes.slice(1, 33);
}
