// @vitest-environment node
// Iteración 13 — D6 (specs/PLAN.md): el validador del navegador recompone la
// raíz y el JSON de metadatos exactamente como el servidor. Mismos vectores
// que tests/Feature/Sealing/MerkleTreeTest.php, generados con Python.
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { canonicalJson, merkleRoot, sha256Hex, verifyProof } from './merkle.js';

const vectors = JSON.parse(readFileSync(new URL('../../../tests/fixtures/merkle/vectors.json', import.meta.url), 'utf8'));

describe('árbol de Merkle del sellado', () => {
    it.each(vectors.trees.map((tree) => [tree.name, tree]))('calcula la raíz y verifica cada prueba (%s)', async (_, tree) => {
        expect(await merkleRoot(tree.leaves)).toBe(tree.root);

        for (const [index, leaf] of tree.leaves.entries()) {
            expect(await verifyProof(leaf, tree.proofs[index], tree.root)).toBe(true);
        }
    });

    it('rechaza una prueba para una hoja que no está en el árbol', async () => {
        const tree = vectors.trees[2];
        const forged = await sha256Hex(new TextEncoder().encode('archivo que nunca se selló'));

        expect(await verifyProof(forged, tree.proofs[0], tree.root)).toBe(false);
    });
});

describe('JSON canónico de metadatos', () => {
    it.each(vectors.metadata.map((c) => [c.name, c]))('se escribe byte a byte igual que en el servidor (%s)', async (_, c) => {
        const json = canonicalJson(c.fields);

        expect(json).toBe(c.canonical_json);
        expect(await sha256Hex(new TextEncoder().encode(json))).toBe(c.sha256);
    });
});
