<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * It. 58 (US-059-LEG, US-035): where the citizen says a work without location
 * is — the place of the work, not theirs —, and when the Administrador
 * confirmed it as the official location. Until then, the work has no pin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizen_reports', function (Blueprint $table) {
            $table->decimal('proposed_latitude', 10, 7)->nullable();
            $table->decimal('proposed_longitude', 10, 7)->nullable();
            $table->timestamp('location_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('citizen_reports', function (Blueprint $table) {
            $table->dropColumn(['proposed_latitude', 'proposed_longitude', 'location_confirmed_at']);
        });
    }
};
