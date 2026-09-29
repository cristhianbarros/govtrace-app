<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * US-003b (it. 33): cuándo se dio de baja una organización — desde ahí
 * corren los 5 años de retención de sus archivos — y cuándo se borraron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('decommissioned_at')->nullable();
            $table->timestamp('evidence_files_purged_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['decommissioned_at', 'evidence_files_purged_at']);
        });
    }
};
