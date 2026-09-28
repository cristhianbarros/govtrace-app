<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * US-001: a tenant IS an organization in GovTrace (specs/SPEC.md, decisión
 * de tenancy). Real columns, not the "data" JSON blob the stancl stub
 * ships with — nit must be queryable for the uniqueness check in
 * RegisterOrganization.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('nit')->unique()->after('id');
            $table->string('name')->after('nit');
            $table->string('status')->default('active')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['nit', 'name', 'status']);
        });
    }
};
