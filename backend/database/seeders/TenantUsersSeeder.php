<?php

namespace Database\Seeders;

use App\Models\User;
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
            ]
        );

        // 🚀 FIX: Set the property for logic, but DON'T call save() after setting it.
        $admin->guard_name = $guard;
        $admin->assignRole('Admin');

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
                    'email_verified_at' => now()
                ]
            );

            // 🚀 FIX: Setting this property allows assignRole to find the right permission
            // without needing a column in the DB.
            $user->guard_name = $guard;
            $user->assignRole($member['role']);
        }

        // 3. General Staff
        User::factory(3)->create(['password' => Hash::make('password')])
            ->each(function ($u) use ($guard) {
                $u->guard_name = $guard;
                $u->assignRole('Employee');
            });
    }
}
