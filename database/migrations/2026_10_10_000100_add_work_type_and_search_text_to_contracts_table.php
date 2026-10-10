<?php

use App\Domain\Contracts\SearchText;
use App\Domain\Contracts\WorkType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Iteración 47a — Encontrar la obra en campo: el tipo de obra (US-016) y el
 * texto de la búsqueda sin tildes ni mayúsculas (V19), en los contratos y en
 * su archivo (US-048-MNT), que guarda las mismas columnas. Los ya guardados
 * se calculan aquí con lo que tienen; el código UNSPSC llega con la próxima
 * sincronización, que vuelve a escribir cada contrato del territorio.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['contracts', 'archived_contracts'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('work_type', 20)->nullable();
                $table->text('search_text')->nullable();
            });

            DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    $payload = json_decode((string) $row->raw_payload, true) ?: [];
                    DB::table($table)->where('id', $row->id)->update([
                        'work_type' => WorkType::classify($row->object, $payload['codigo_de_categoria_principal'] ?? null)->value,
                        'search_text' => SearchText::of($row->object, $row->contractor_name, $row->process_number, $row->entity_name),
                    ]);
                }
            });
        }

        // La lista del veedor filtra por municipio y ordena por la fecha de fin.
        Schema::table('contracts', function (Blueprint $table) {
            $table->index(['municipality_code', 'end_date']);
            $table->index('work_type');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex(['municipality_code', 'end_date']);
            $table->dropIndex(['work_type']);
        });

        foreach (['contracts', 'archived_contracts'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['work_type', 'search_text']);
            });
        }
    }
};
