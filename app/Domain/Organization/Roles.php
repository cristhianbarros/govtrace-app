<?php

namespace App\Domain\Organization;

/**
 * The two roles that exist inside every organization's own database (D3).
 * The Super Administrator is not one of these — it lives outside tenancy,
 * in the central "users" table, and needs no role at all (there is only
 * one kind of central user).
 */
enum Roles: string
{
    case Administrator = 'Administrador de Organización';
    case Observer = 'Veedor de Campo';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }
}
