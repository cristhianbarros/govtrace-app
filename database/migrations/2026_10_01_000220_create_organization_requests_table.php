<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Iteración 43k (V10, US-062-ALT): las solicitudes de alta que las veedurías
 * envían desde el Inicio, para el Super Administrador. Una decidida se borra
 * a los 30 días (App\Jobs\PurgeOrganizationRequests).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('contact_email', 150);
            $table->string('registration_number', 100);
            $table->string('registration_authority', 150);
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->text('rejection_reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('tenant_id')->nullable(); // la organización que salió de aprobarla
            $table->timestamp('data_authorized_at');
            $table->string('data_policy_version');
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_requests');
    }
};
