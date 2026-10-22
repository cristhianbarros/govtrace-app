<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * It. 69 (US-071, R-PRIV-07): la clave de cada día, en hora de Colombia, con
 * la que se calcula la huella de quien ve la página de una obra. Aleatoria, la
 * misma para todas las organizaciones y todos los workers; al día siguiente se
 * borra (ForgetWorksiteVisitors), y las copias de respaldo no la llevan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_keys', function (Blueprint $table) {
            $table->date('day')->primary();
            $table->string('key', 64);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_keys');
    }
};
