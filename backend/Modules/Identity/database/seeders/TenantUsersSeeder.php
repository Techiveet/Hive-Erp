<?php

namespace Modules\Identity\Database\Seeders;

use Modules\Identity\Models\User;
use Modules\Identity\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class TenantUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cache for the current tenant context
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $tenantId = tenant('id');
        $guard = 'tenant';

        // 1. Company Admin
        $admin = User::updateOrCreate(
            ['email' => "admin@{$tenantId}.com"],
            [
                'name' => ucfirst($tenantId) . ' Controller',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
                // 🚀 FIX: Explicitly force 2FA to remain disabled
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
            ]
        );

        $admin->guard_name = $guard;
        $admin->syncRoles(['Super Admin']);
        $admin->syncPermissions(
            Permission::where('guard_name', $guard)->pluck('name')->all()
        );

        // 2. Department Heads
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
                    // 🚀 FIX: Explicitly force 2FA to remain disabled
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                ]
            );

            $user->guard_name = $guard;
            $user->syncRoles([$member['role']]);
        }

        // 3. General Staff
        User::factory(3)->create([
            'password' => Hash::make('password'),
            'is_active' => true,
            // 🚀 FIX: Explicitly force 2FA to remain disabled
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ])->each(function ($u) use ($guard) {
            $u->guard_name = $guard;
            $u->syncRoles(['Employee']);
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
