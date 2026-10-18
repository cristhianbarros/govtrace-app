<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * It. 66a (US-069, R-LEG-12): until when the veeduría committed to answer (its
 * term when the report arrived, in business days), when it first acted on it
 * (answered, asked for a visit or discarded: that stops the term), and when
 * the citizen was told the way to the Contraloría.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizen_reports', function (Blueprint $table) {
            $table->date('due_on')->nullable();
            $table->timestamp('first_action_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('citizen_reports', function (Blueprint $table) {
            $table->dropColumn(['due_on', 'first_action_at', 'escalated_at']);
        });
    }
};
