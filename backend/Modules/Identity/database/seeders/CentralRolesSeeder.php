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
            'view_tenants', 'manage_tenants', 'provision_tenants', 'edit_tenants', 'suspend_tenants', 'delete_tenants',

            // Identity & Access (Granular)
            'view_profile', 'edit_profile',
            'view_users', 'create_users', 'edit_users', 'delete_users', 'manage_users',
            'view_roles', 'create_roles', 'edit_roles', 'delete_roles', 'manage_roles',
            'view_permissions', // 🚀 Dedicated capability for the Dictionary Tab

            // Audit Logs
            'view_logs', 'export_logs', 'manage_log_settings', 'archive_logs', 'delete_archived_logs',

            // Alerts & Backups
            'view_alerts', 'manage_alerts', 'view_backups', 'manage_backups',

            // System & Settings
            'view_system_dashboard', 'manage_system_settings', 'view_storage', 'manage_storage',
            'manage_brand_settings', 'manage_general_settings', 'manage_localization', 'view_api_docs',
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
                'view_system_dashboard', 'view_profile', 'edit_profile', 'view_api_docs',
                'view_tenants', 'view_users', 'view_roles', 'view_permissions', 'view_logs', 'export_logs', 'view_alerts', 'view_storage'
            ], // Strictly read-only across all tabs

            'Support Specialist' => [
                'view_system_dashboard', 'view_profile', 'edit_profile', 'view_api_docs',
                'view_tenants', 'view_users', 'edit_users', 'view_alerts', 'view_storage'
            ], // Can view system and reset user passwords/status, but cannot touch roles

            'Billing Admin' => [
                'view_system_dashboard', 'view_profile', 'edit_profile', 'view_api_docs',
                'view_tenants', 'suspend_tenants', 'view_alerts'
            ], // Financial operations
        ];

        foreach ($roles as $roleName => $rolePerms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guard]);
            $role->syncPermissions($rolePerms);
        }
    }
}
