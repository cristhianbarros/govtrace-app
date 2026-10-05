<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * It. 46h (US-059-LEG): a citizen's report carries from 1 to 3 photos, not one.
 * The photo of the reports that already exist becomes the first of its list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizen_reports', function (Blueprint $table) {
            $table->json('photo_paths')->default('[]');
        });

        DB::table('citizen_reports')->whereNotNull('photo_path')->orderBy('id')->each(function ($report) {
            DB::table('citizen_reports')->where('id', $report->id)->update(['photo_paths' => json_encode([$report->photo_path])]);
        });

        Schema::table('citizen_reports', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('citizen_reports', function (Blueprint $table) {
            $table->string('photo_path')->nullable();
        });

        DB::table('citizen_reports')->orderBy('id')->each(function ($report) {
            DB::table('citizen_reports')->where('id', $report->id)->update(['photo_path' => json_decode($report->photo_paths, true)[0] ?? null]);
        });

        Schema::table('citizen_reports', function (Blueprint $table) {
            $table->dropColumn('photo_paths');
        });
    }
};
