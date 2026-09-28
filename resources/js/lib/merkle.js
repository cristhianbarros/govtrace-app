// D6 (it. 13): el mismo árbol de Merkle y el mismo JSON canónico que sella el
// servidor (app/Domain/Sealing/MerkleTree.php y SealedMetadata.php), para que
// el validador del navegador recomponga la raíz sin depender de GovTrace
// (R-BLK-05). Ambos lados se prueban con tests/fixtures/merkle/vectors.json.
//
// Hoja: un SHA-256 de 32 bytes. Nodo: SHA-256(menor || mayor), comparando
// bytes. Un nodo sin pareja sube tal cual. La prueba: los hermanos, de abajo
// hacia arriba.

const toHex = (bytes) => Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');

function hexToBytes(hex) {
    if (!/^[0-9a-f]{64}$/i.test(hex)) {
        throw new Error(`No es un SHA-256 en hexadecimal: ${hex}`);
    }
    return Uint8Array.from(hex.match(/../g), (byte) => parseInt(byte, 16));
}

function compareBytes(a, b) {
    for (let i = 0; i < a.length; i++) {
        if (a[i] !== b[i]) {
            return a[i] - b[i];
        }
    }
    return 0;
}

async function digest(bytes) {
    return new Uint8Array(await crypto.subtle.digest('SHA-256', bytes));
}

async function parent(a, b) {
    const [low, high] = compareBytes(a, b) <= 0 ? [a, b] : [b, a];
    const joined = new Uint8Array(64);
    joined.set(low);
    joined.set(high, 32);
    return digest(joined);
}

export async function sha256Hex(bytes) {
    return toHex(await digest(bytes));
}

export async function merkleRoot(leavesHex) {
    let level = leavesHex.map(hexToBytes);
    while (level.length > 1) {
        const next = [];
        for (let i = 0; i < level.length; i += 2) {
            next.push(i + 1 < level.length ? await parent(level[i], level[i + 1]) : level[i]);
        }
        level = next;
    }
    return toHex(level[0]);
}

export async function verifyProof(leafHex, proofHex, rootHex) {
    let node = hexToBytes(leafHex);
    for (const sibling of proofHex) {
        node = await parent(node, hexToBytes(sibling));
    }
    return toHex(node) === rootHex.toLowerCase();
}

// Claves ordenadas, sin espacios, UTF-8 sin escapar: JSON.stringify ya deja
// sin escapar "/", los caracteres no ASCII y U+2028/2029, como el servidor.
export function canonicalJson(fields) {
    const sorted = {};
    for (const key of Object.keys(fields).sort()) {
        sorted[key] = fields[key];
    }
    return JSON.stringify(sorted);
}
