<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * It. 69 (US-071, R-PRIV-07): how many people see the page of each work. The
 * fingerprints of the day keep one per person, work and day, never the IP or
 * the browser; the next day they are deleted, and the backups do not carry
 * them. What stays is the number of people of each work on each day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worksite_visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worksite_id')->constrained('worksites')->cascadeOnDelete();
            $table->date('day');
            $table->string('fingerprint', 64);

            $table->unique(['worksite_id', 'day', 'fingerprint']);
            $table->index('day');
        });

        Schema::create('worksite_visit_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worksite_id')->constrained('worksites')->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('visitors');

            $table->unique(['worksite_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worksite_visit_days');
        Schema::dropIfExists('worksite_visitors');
    }
};
