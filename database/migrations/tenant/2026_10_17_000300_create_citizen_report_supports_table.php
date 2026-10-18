<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * It. 66b (US-069): "Yo también lo vi" — a citizen who joins what another one
 * already reported on a work. Their email, encrypted like the reporter's
 * (R-LEG-10): it only serves to tell them what the veeduría does. Its
 * fingerprint keeps one per person and work.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citizen_report_supports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('citizen_report_id')->constrained('citizen_reports')->cascadeOnDelete();
            $table->text('email')->nullable();
            $table->string('email_hash', 64)->nullable()->index();
            $table->timestamp('data_authorized_at');
            $table->string('data_policy_version', 40);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citizen_report_supports');
    }
};
