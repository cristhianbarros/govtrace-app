<?php

namespace App\Domain\Sealing;

use InvalidArgumentException;

/**
 * D6 / R-BLK-05: el árbol de Merkle de un reporte. Una sola raíz se sella,
 * y cada archivo guarda su prueba de inclusión para que el navegador del
 * Verificador recomponga la raíz sin depender de GovTrace.
 *
 * Esquema único, el mismo en JavaScript (tools/verify/lib/merkle.mjs: el
 * verificador independiente, y el validador del navegador lo reexporta) y
 * probado con los vectores de tests/fixtures/merkle:
 * - hoja: un SHA-256 de 32 bytes;
 * - nodo: SHA-256(menor || mayor), comparando bytes ("pares ordenados"),
 *   así la prueba no necesita decir de qué lado va cada hermano;
 * - un nodo sin pareja sube tal cual al nivel siguiente;
 * - la prueba es la lista de hermanos, de abajo hacia arriba.
 */
final class MerkleTree
{
    /** @param  list<list<string>>  $levels  nodos crudos de 32 bytes, de las hojas a la raíz */
    private function __construct(private readonly array $levels) {}

    /** @param  list<string>  $leaves  SHA-256 en hexadecimal */
    public static function fromLeaves(array $leaves): self
    {
        if ($leaves === []) {
            throw new InvalidArgumentException('Un árbol de Merkle necesita al menos una hoja.');
        }

        $level = array_map(self::bytes(...), array_values($leaves));
        $levels = [$level];

        while (count($level) > 1) {
            $next = [];
            foreach (array_chunk($level, 2) as $pair) {
                $next[] = count($pair) === 2 ? self::parent($pair[0], $pair[1]) : $pair[0];
            }
            $levels[] = $level = $next;
        }

        return new self($levels);
    }

    public function root(): string
    {
        return bin2hex($this->levels[array_key_last($this->levels)][0]);
    }

    /** @return list<string> los hermanos de la hoja $index, de abajo hacia arriba */
    public function proof(int $index): array
    {
        $proof = [];

        foreach (array_slice($this->levels, 0, -1) as $level) {
            $sibling = $index ^ 1;
            if (isset($level[$sibling])) {
                $proof[] = bin2hex($level[$sibling]);
            }
            $index = intdiv($index, 2);
        }

        return $proof;
    }

    /** @param  list<string>  $proof */
    public static function verify(string $leaf, array $proof, string $root): bool
    {
        $node = self::bytes($leaf);

        foreach ($proof as $sibling) {
            $node = self::parent($node, self::bytes($sibling));
        }

        return hash_equals(strtolower($root), bin2hex($node));
    }

    private static function parent(string $a, string $b): string
    {
        return hash('sha256', strcmp($a, $b) <= 0 ? $a.$b : $b.$a, true);
    }

    private static function bytes(string $hex): string
    {
        if (! preg_match('/^[0-9a-f]{64}$/i', $hex)) {
            throw new InvalidArgumentException("No es un SHA-256 en hexadecimal: {$hex}");
        }

        return hex2bin($hex);
    }
}
