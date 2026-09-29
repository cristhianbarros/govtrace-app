// Bytes <-> hexadecimal, sin Buffer: el mismo código corre en Node y en el navegador.

export const toHex = (bytes) => Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');

export function fromHex(hex, length) {
    if (typeof hex !== 'string' || !new RegExp(`^[0-9a-f]{${length * 2}}$`, 'i').test(hex)) {
        throw new Error(`No son ${length} bytes en hexadecimal: ${hex}`);
    }
    return Uint8Array.from(hex.match(/../g), (byte) => parseInt(byte, 16));
}
