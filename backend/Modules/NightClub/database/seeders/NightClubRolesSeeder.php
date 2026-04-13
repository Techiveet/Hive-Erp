<?php

namespace Modules\NightClub\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class NightClubRolesSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'tenant';

        $permissions = [
            'view_nightclub_tables',
            'create_nightclub_tables',
            'edit_nightclub_tables',
            'delete_nightclub_tables',
            'view_nightclub_reservations',
            'create_nightclub_reservations',
            'edit_nightclub_reservations',
            'delete_nightclub_reservations',
            'confirm_nightclub_reservations',
            'complete_nightclub_reservations',
            'assign_nightclub_staff',
            'view_nightclub_service_orders',
            'create_nightclub_service_orders',
            'edit_nightclub_service_orders',
            'close_nightclub_service_orders',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => $guard]);
        }

        $manager = Role::firstOrCreate(['name' => 'Club Manager', 'guard_name' => $guard]);
        $manager->syncPermissions($permissions);

        $waiter = Role::firstOrCreate(['name' => 'Waiter', 'guard_name' => $guard]);
        $waiter->syncPermissions([
            'view_nightclub_tables',
            'view_nightclub_reservations',
            'complete_nightclub_reservations',
            'view_nightclub_service_orders',
            'create_nightclub_service_orders',
            'close_nightclub_service_orders',
        ]);

        $bouncer = Role::firstOrCreate(['name' => 'Bouncer', 'guard_name' => $guard]);
        $bouncer->syncPermissions([
            'view_nightclub_tables',
            'view_nightclub_reservations',
            'confirm_nightclub_reservations',
        ]);

        $hostess = Role::firstOrCreate(['name' => 'Hostess', 'guard_name' => $guard]);
        $hostess->syncPermissions([
            'view_nightclub_tables',
            'view_nightclub_reservations',
            'create_nightclub_reservations',
            'confirm_nightclub_reservations',
            'complete_nightclub_reservations',
            'view_nightclub_service_orders',
        ]);

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => $guard]);
        $superAdmin->syncPermissions(Permission::where('guard_name', $guard)->get());
    }
}
