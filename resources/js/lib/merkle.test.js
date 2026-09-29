// @vitest-environment node
// El validador del navegador usa el árbol de Merkle del verificador
// independiente, no una copia: los vectores de la it. 13 se prueban allí
// (tools/verify/test/merkle.test.mjs).
import { expect, it } from 'vitest';
import * as script from '../../../tools/verify/lib/merkle.mjs';
import * as browser from './merkle.js';

it('is the very same implementation as the independent verifier', () => {
    for (const name of ['canonicalJson', 'merkleRoot', 'sha256Hex', 'verifyProof']) {
        expect(browser[name]).toBe(script[name]);
    }
});
