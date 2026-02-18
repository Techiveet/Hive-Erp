<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CentralRolesSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web'; // Central Guard

        // 1. Central Permissions
        $permissions = ['manage tenants', 'view dashboard', 'access billing'];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => $guard]);
        }

        // 2. Central Roles
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => $guard]);
        $support = Role::firstOrCreate(['name' => 'Support', 'guard_name' => $guard]);

        // 3. Sync Permissions
        $superAdmin->syncPermissions(Permission::where('guard_name', $guard)->get());
        $support->givePermissionTo('view dashboard');
    }
}
