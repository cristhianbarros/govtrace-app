<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipalities', function (Blueprint $table) {
            $table->string('code', 5)->primary();
            $table->string('name');
            $table->string('department_code', 2);
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
            $table->timestamps();

            $table->foreign('department_code')->references('code')->on('departments');
            // The matcher normalizes a free-text SECOP name before comparing
            // (accents/case stripped in PHP), so this index only speeds up
            // the exact-code lookups the rest of the app does.
            $table->index('department_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipalities');
    }
};
