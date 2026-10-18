<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * It. 68 (US-059-LEG): what the citizen wrote to locate a work without
 * location — its address or a place nearby —, for the veeduría. Not public.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizen_reports', function (Blueprint $table) {
            $table->string('place_reference', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('citizen_reports', function (Blueprint $table) {
            $table->dropColumn('place_reference');
        });
    }
};
