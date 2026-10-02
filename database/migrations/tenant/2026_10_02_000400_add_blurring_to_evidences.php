<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * It. 46e (R-PRIV-05): what the phone says it blurred in each photo before
 * computing its hash. Null for a PDF, and for a photo sent before 46e.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidences', function (Blueprint $table) {
            $table->unsignedSmallInteger('blurred_faces')->nullable();
            $table->unsignedSmallInteger('dismissed_faces')->nullable();
            $table->unsignedSmallInteger('blurred_by_hand')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('evidences', function (Blueprint $table) {
            $table->dropColumn(['blurred_faces', 'dismissed_faces', 'blurred_by_hand']);
        });
    }
};
