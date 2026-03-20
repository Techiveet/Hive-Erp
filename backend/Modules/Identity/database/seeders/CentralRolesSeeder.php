<?php

namespace Modules\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class CentralRolesSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cache to prevent Spatie conflicts during seeding
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        $permissions = [
            // Tenant Nodes
            'view_tenants', 'provision_tenants', 'edit_tenants', 'suspend_tenants', 'delete_tenants',

            // Identity & Access (Granular)
            'view_users', 'create_users', 'edit_users', 'delete_users', 'manage_users',
            'view_roles', 'create_roles', 'edit_roles', 'delete_roles', 'manage_roles',
            'view_permissions', // 🚀 Dedicated capability for the Dictionary Tab

            // Audit Logs
            'view_logs', 'export_logs',

            // System & Settings
            'view_system_dashboard', 'manage_system_settings', 'manage_storage'
        ];

        // 1. Establish the Capability Dictionary
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => $guard]);
        }

        // 2. Establish Super Admin (God Mode)
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => $guard]);
        $superAdmin->syncPermissions(Permission::where('guard_name', $guard)->get());

        // 3. Establish Standard Roles
        $roles = [
            'Security Auditor' => [
                'view_system_dashboard', 'view_tenants', 'view_users', 'view_roles', 'view_permissions', 'view_logs'
            ], // Strictly read-only across all tabs

            'Support Specialist' => [
                'view_system_dashboard', 'view_tenants', 'view_users', 'edit_users'
            ], // Can view system and reset user passwords/status, but cannot touch roles

            'Billing Admin' => [
                'view_system_dashboard', 'view_tenants', 'suspend_tenants'
            ], // Financial operations
        ];

        foreach ($roles as $roleName => $rolePerms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guard]);
            $role->syncPermissions($rolePerms);
        }
    }
}
