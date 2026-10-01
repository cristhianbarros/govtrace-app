<?php

use App\Application\Contracts\ProcessSecopContractRow;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Iteración 45c: lo ya guardado de SECOP II, recortado a los campos que
 * GovTrace usa (ProcessSecopContractRow::KEPT_FIELDS), en los contratos
 * vigentes y en los archivados. Lo que se quita no vuelve: SECOP lo sigue
 * publicando, y la próxima sincronización ya llega recortada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $kept = '{'.implode(',', ProcessSecopContractRow::KEPT_FIELDS).'}';

        foreach (['contracts', 'archived_contracts'] as $table) {
            DB::update(
                "update {$table} set raw_payload = (select json_object_agg(key, value) from json_each(raw_payload) where key = any(?::text[])) where raw_payload is not null",
                [$kept],
            );
        }
    }

    public function down(): void
    {
        // Nada que deshacer: los campos quitados no se guardan en otra parte.
    }
};
