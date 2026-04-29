<?php

namespace Modules\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\User;
use Modules\Identity\Support\AccessControlCatalog;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantUsersSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $tenantId = tenant('id');
        $guard = 'tenant';

        $admin = User::updateOrCreate(
            ['email' => "admin@{$tenantId}.com"],
            [
                'name' => ucfirst($tenantId).' Controller',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
            ]
        );

        $admin->guard_name = $guard;

        if ($tenantId === 'techive') {
            $role = Role::firstOrCreate(['name' => 'Software Development Admin', 'guard_name' => $guard]);
            $permissions = AccessControlCatalog::softwareDevelopmentTenantAdminPermissions();
            $role->syncPermissions($permissions);
            $admin->syncRoles([$role->name]);
            $admin->syncPermissions($permissions);
        } else {
            $admin->syncRoles(['Super Admin']);
            $admin->syncPermissions(
                Permission::where('guard_name', $guard)->pluck('name')->all()
            );
        }

        $staff = [
            ['email' => "hr@{$tenantId}.com", 'name' => 'Sarah HR', 'role' => 'HR Manager'],
            ['email' => "finance@{$tenantId}.com", 'name' => 'Mike Money', 'role' => 'Finance Controller'],
            ['email' => "logistics@{$tenantId}.com", 'name' => 'Tom Transport', 'role' => 'Logistics Coordinator'],
        ];

        foreach ($staff as $member) {
            $user = User::updateOrCreate(
                ['email' => $member['email']],
                [
                    'name' => $member['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                ]
            );

            $user->guard_name = $guard;
            $user->syncRoles([$member['role']]);
        }

        for ($i = 1; $i <= 3; $i++) {
            $user = User::updateOrCreate(
                ['email' => "employee{$i}@{$tenantId}.com"],
                [
                    'name' => 'Employee '.Str::upper(Str::random(4)),
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                ]
            );

            $user->guard_name = $guard;
            $user->syncRoles(['Employee']);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
