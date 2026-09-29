<?php

use App\Domain\Sealing\MerkleTree;
use App\Domain\Sealing\SealedMetadata;

/*
 * Iteración 13 — D6 (specs/PLAN.md): el árbol de Merkle y el JSON de
 * metadatos contra los vectores compartidos de tests/fixtures/merkle,
 * generados con una implementación independiente (Python). El validador
 * del navegador y el verificador independiente comparten implementación, que
 * prueba los mismos vectores (tools/verify/test/merkle.test.mjs).
 */

function merkleVectors(): array
{
    // __DIR__ y no base_path(): los datasets se leen antes de que arranque la app.
    return json_decode(file_get_contents(__DIR__.'/../../fixtures/merkle/vectors.json'), true);
}

it('computes the root and the inclusion proofs of the shared vectors', function (array $tree) {
    $merkle = MerkleTree::fromLeaves($tree['leaves']);

    expect($merkle->root())->toBe($tree['root']);

    foreach ($tree['leaves'] as $index => $leaf) {
        expect($merkle->proof($index))->toBe($tree['proofs'][$index])
            ->and(MerkleTree::verify($leaf, $tree['proofs'][$index], $tree['root']))->toBeTrue();
    }
})->with(fn () => collect(merkleVectors()['trees'])->mapWithKeys(fn ($tree) => [$tree['name'] => [$tree]])->all());

it('rejects a proof for a leaf that is not in the tree', function () {
    $tree = merkleVectors()['trees'][2];
    $forged = hash('sha256', 'archivo que nunca se selló');

    expect(MerkleTree::verify($forged, $tree['proofs'][0], $tree['root']))->toBeFalse();
});

it('writes the canonical metadata JSON byte for byte, as the browser will recompute it', function (array $case) {
    $metadata = SealedMetadata::fromFields($case['fields']);

    expect($metadata->json)->toBe($case['canonical_json'])
        ->and($metadata->sha256())->toBe($case['sha256']);
})->with(fn () => collect(merkleVectors()['metadata'])->mapWithKeys(fn ($case) => [$case['name'] => [$case]])->all());
