<?php

use App\Domain\Contracts\WorkType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Iteración 67 — una obra con dos partes (US-016): todos los tipos que nombra
 * el objeto de un contrato, el principal primero, en los contratos y en su
 * archivo (US-048-MNT), que guarda las mismas columnas. El filtro del veedor
 * la encuentra por cualquiera de ellos.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['contracts', 'archived_contracts'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->jsonb('work_types')->nullable();
            });

            DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    $payload = json_decode((string) $row->raw_payload, true) ?: [];
                    $types = WorkType::classifyAll($row->object, $payload['codigo_de_categoria_principal'] ?? null);
                    DB::table($table)->where('id', $row->id)->update([
                        'work_types' => json_encode(array_map(fn (WorkType $type) => $type->value, $types)),
                    ]);
                }
            });
        }

        DB::statement('create index contracts_work_types_index on contracts using gin (work_types)');
    }

    public function down(): void
    {
        DB::statement('drop index if exists contracts_work_types_index');

        foreach (['contracts', 'archived_contracts'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('work_types');
            });
        }
    }
};
