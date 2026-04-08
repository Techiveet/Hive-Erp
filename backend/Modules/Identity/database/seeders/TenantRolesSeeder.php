<?php

namespace Modules\Identity\Database\Seeders;

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
            'view_system_dashboard',
            'view_profile', 'edit_profile',
            'view_users', 'create_users', 'edit_users', 'delete_users', 'manage_users',
            'view_roles', 'create_roles', 'edit_roles', 'delete_roles', 'manage_roles',
            'view_permissions',
            'view_logs', 'export_logs', 'manage_log_settings', 'archive_logs', 'delete_archived_logs',
            'view_alerts', 'manage_alerts',
            'manage_system_settings',
            'view_storage',
            'manage_storage',
            'view_module_subscriptions',
            'manage_module_subscriptions',
            'manage_brand_settings', 'manage_general_settings', 'manage_localization', 'view_api_docs',
            'view_invoices', 'manage_invoices',
            'view_inventory', 'manage_inventory',
            'manage_fleet'
        ];

        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]);
        }

        $roles = [
            'HR Manager' => ['view_system_dashboard', 'view_profile', 'edit_profile', 'view_api_docs', 'view_alerts', 'view_users', 'create_users', 'edit_users', 'view_roles', 'view_storage', 'view_module_subscriptions'],
            'Finance Controller' => ['view_system_dashboard', 'view_profile', 'edit_profile', 'view_api_docs', 'view_alerts', 'view_invoices', 'manage_invoices', 'view_logs', 'export_logs', 'view_module_subscriptions'],
            'Logistics Coordinator' => ['view_system_dashboard', 'view_profile', 'edit_profile', 'view_api_docs', 'view_alerts', 'view_inventory', 'manage_inventory', 'manage_fleet', 'view_module_subscriptions'],
            'Employee' => ['view_system_dashboard', 'view_profile', 'edit_profile', 'view_api_docs', 'view_alerts', 'view_users', 'view_module_subscriptions'],
        ];

        foreach ($roles as $roleName => $rolePerms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guard]);
            $role->syncPermissions($rolePerms);
        }

        // 🚀 SUPER ADMIN: GETS ALL PERMISSIONS IN DB dynamically
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => $guard]);
        $superAdmin->syncPermissions(Permission::where('guard_name', $guard)->get());
    }
}
