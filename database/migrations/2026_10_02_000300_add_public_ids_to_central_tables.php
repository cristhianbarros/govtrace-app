<?php

use App\Infrastructure\Database\PublicIdColumn;
use Illuminate\Database\Migrations\Migration;

/** It. 46c (US-064-SEC, R-SEC-08): the Super Administradores and the requests for an alta. */
return new class extends Migration
{
    private const TABLES = ['users', 'organization_requests'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            PublicIdColumn::addTo($table);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            PublicIdColumn::dropFrom($table);
        }
    }
};
