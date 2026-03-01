<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class TenantRolesSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'tenant';

        $perms = [
            'view_users', 'create_users', 'edit_users', 'delete_users', 'manage_users',
            'view_roles', 'create_roles', 'edit_roles', 'delete_roles', 'manage_roles',
            'view_permissions', // 🚀 Added to Tenant Dictionary

            'view_logs', 'export_logs', 'manage_storage',
            'view_invoices', 'manage_invoices',
            'view_inventory', 'manage_inventory',
            'manage_fleet'
        ];

        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]);
        }

        $roles = [
            'Admin' => $perms,
            'HR Manager' => ['view_users', 'create_users', 'edit_users', 'view_roles'],
            'Finance Controller' => ['view_invoices', 'manage_invoices', 'view_logs'],
            'Logistics Coordinator' => ['view_inventory', 'manage_inventory', 'manage_fleet'],
            'Employee' => ['view_users'],
        ];

        foreach ($roles as $roleName => $rolePerms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guard]);
            $role->syncPermissions($rolePerms);
        }
    }
}
