<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-057-LEG (it. 44c): when a veedor declared having none of the
 * impediments of article 19 of Ley 850 de 2003. Null: not yet — no report
 * of theirs is received until they do (R-LEG-05).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('impediments_declared_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('impediments_declared_at');
        });
    }
};
