<?php

use App\Infrastructure\Database\PublicIdColumn;
use Illuminate\Database\Migrations\Migration;

/**
 * It. 46c (US-064-SEC, R-SEC-08): the public id of the records the URL and
 * the API name. The numeric keys stay: the sealed worksite reference is
 * computed from them (R-BLK-02).
 */
return new class extends Migration
{
    private const TABLES = ['worksites', 'reports', 'evidences', 'citizen_reports', 'users'];

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
