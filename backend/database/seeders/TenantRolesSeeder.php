<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class TenantRolesSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'tenant'; // ⚡ CRITICAL: Use the tenant guard

        $perms = ['create invoice', 'delete invoice', 'manage employees', 'view analytics'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]);
        }

        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => $guard]);
        $manager = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => $guard]);
        $employee = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => $guard]);

        $admin->syncPermissions(Permission::where('guard_name', $guard)->get());
        $manager->syncPermissions(['create invoice', 'manage employees']);
        $employee->givePermissionTo('create invoice');
    }
}
