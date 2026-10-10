<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Iteración 47a: las obras cercanas (US-019) se buscan en una caja de
 * latitud y longitud alrededor del veedor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worksites', function (Blueprint $table) {
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('worksites', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
        });
    }
};
