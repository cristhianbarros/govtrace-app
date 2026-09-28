<?php

use App\Domain\Organization\Roles;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

/*
 * The two roles every organization has (D3, US-002, US-005). A migration,
 * not a seeder run separately: this way `Jobs\MigrateDatabase` (already
 * wired on TenantCreated) is the only step needed — a freshly registered
 * organization can assign its Administrador inicial right away.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Roles::all() as $role) {
            Role::query()->firstOrCreate(['name' => $role, 'guard_name' => 'tenant']);
        }
    }

    public function down(): void
    {
        Role::query()->whereIn('name', Roles::all())->delete();
    }
};
