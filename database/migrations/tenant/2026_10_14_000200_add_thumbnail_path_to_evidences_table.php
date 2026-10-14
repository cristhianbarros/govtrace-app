<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * It. 60 (US-029): the thumbnail of a published photo — a light copy to look
 * at, never what was sealed. Null until the evidence is published, and again
 * once it is withdrawn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidences', function (Blueprint $table) {
            $table->string('thumbnail_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('evidences', function (Blueprint $table) {
            $table->dropColumn('thumbnail_path');
        });
    }
};
