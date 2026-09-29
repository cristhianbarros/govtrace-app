// D6: el mismo árbol de Merkle que sella el servidor. Vive en el verificador
// independiente (tools/verify), que no depende de nada de GovTrace; el
// validador del navegador usa esa misma implementación.
export { canonicalJson, merkleRoot, sha256Hex, verifyProof } from '../../../tools/verify/lib/merkle.mjs';
