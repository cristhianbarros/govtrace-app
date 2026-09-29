<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * US-004 (it. 32): la comisión que la red le cobró a la cuenta
 * patrocinadora por el sello, en stroops (1 XLM = 10.000.000). null en los
 * sellados antes de esta migración y si la red ya no recuerda la
 * transacción (el RPC guarda 7 días).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_seals', function (Blueprint $table) {
            $table->unsignedBigInteger('fee_stroops')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('report_seals', function (Blueprint $table) {
            $table->dropColumn('fee_stroops');
        });
    }
};
