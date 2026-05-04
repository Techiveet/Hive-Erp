<?php

namespace Modules\Hospitality\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class HospitalityRolesSeeder extends Seeder
{
    private const GUARD = 'tenant';

    private function permissions(): array
    {
        return [
            // Tables
            'view_hospitality_tables',
            'create_hospitality_tables',
            'edit_hospitality_tables',
            'delete_hospitality_tables',
            // Reservations
            'view_hospitality_reservations',
            'create_hospitality_reservations',
            'edit_hospitality_reservations',
            'delete_hospitality_reservations',
            'confirm_hospitality_reservations',
            'complete_hospitality_reservations',
            // Service Orders
            'view_hospitality_service_orders',
            'create_hospitality_service_orders',
            'edit_hospitality_service_orders',
            'close_hospitality_service_orders',
            // Menu
            'view_hospitality_menu',
            'create_hospitality_menu',
            'edit_hospitality_menu',
            'delete_hospitality_menu',
            // Events
            'view_hospitality_events',
            'create_hospitality_events',
            'edit_hospitality_events',
            'delete_hospitality_events',
            // Waitlist
            'view_hospitality_waitlist',
            'create_hospitality_waitlist',
            'edit_hospitality_waitlist',
            'delete_hospitality_waitlist',
            // Customers
            'view_hospitality_customers',
            'create_hospitality_customers',
            'edit_hospitality_customers',
            'delete_hospitality_customers',
            // Staff Shifts
            'view_hospitality_shifts',
            'create_hospitality_shifts',
            'edit_hospitality_shifts',
            'delete_hospitality_shifts',
            // Feedback
            'view_hospitality_feedback',
            'create_hospitality_feedback',
            'edit_hospitality_feedback',
            'delete_hospitality_feedback',
            // Billing
            'view_hospitality_billing',
            'create_hospitality_billing',
        ];
    }

    private function roles(): array
    {
        $all = $this->permissions();

        return [
            'hospitality_manager' => $all,
            'hospitality_host' => [
                'view_hospitality_tables',
                'view_hospitality_reservations',
                'create_hospitality_reservations',
                'edit_hospitality_reservations',
                'confirm_hospitality_reservations',
                'complete_hospitality_reservations',
                'view_hospitality_service_orders',
                'create_hospitality_service_orders',
                'view_hospitality_customers',
                'create_hospitality_customers',
                'view_hospitality_waitlist',
                'create_hospitality_waitlist',
                'edit_hospitality_waitlist',
                'view_hospitality_feedback',
                'create_hospitality_feedback',
                'view_hospitality_billing',
                'create_hospitality_billing',
            ],
            'hospitality_waiter' => [
                'view_hospitality_tables',
                'view_hospitality_reservations',
                'view_hospitality_service_orders',
                'create_hospitality_service_orders',
                'edit_hospitality_service_orders',
                'close_hospitality_service_orders',
                'view_hospitality_menu',
                'view_hospitality_billing',
                'create_hospitality_billing',
            ],
            'hospitality_chef' => [
                'view_hospitality_service_orders',
                'edit_hospitality_service_orders',
                'view_hospitality_menu',
            ],
        ];
    }

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissions() as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => self::GUARD]);
        }

        foreach ($this->roles() as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => self::GUARD]);
            $role->syncPermissions($rolePermissions);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => self::GUARD]);
        $superAdmin->syncPermissions(Permission::where('guard_name', self::GUARD)->get());
    }
}
