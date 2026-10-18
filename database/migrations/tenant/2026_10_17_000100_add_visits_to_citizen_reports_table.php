<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * It. 65b (US-068, R-LEG-11): the veedor the Administrador asked to document
 * a citizen report, and the report of that veedor that documents it. The
 * citizen hears once that a veedor will go (citizen_notified_at), and again
 * when the evidence is published (documented_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizen_reports', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            // "Carlos Rojas no pudo ir", "El veedor ya no está activo": por qué volvió a "Nuevo".
            $table->string('visit_note', 200)->nullable();
            $table->timestamp('citizen_notified_at')->nullable();
            $table->foreignId('documenting_report_id')->nullable()->constrained('reports')->nullOnDelete();
            $table->timestamp('documented_at')->nullable();
            $table->index(['worksite_id', 'assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('citizen_reports', function (Blueprint $table) {
            $table->dropIndex(['worksite_id', 'assigned_to', 'status']);
            $table->dropConstrainedForeignId('documenting_report_id');
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['assigned_at', 'visit_note', 'citizen_notified_at', 'documented_at']);
        });
    }
};
