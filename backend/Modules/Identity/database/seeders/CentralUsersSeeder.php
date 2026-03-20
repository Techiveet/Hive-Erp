<?php

namespace Modules\Identity\Database\Seeders;

use Modules\Identity\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CentralUsersSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        $superAdmin = User::updateOrCreate(
            ['email' => 'super@hive.os'],
            [
                'name' => 'Hive Overlord',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        if (Role::where('name', 'Super Admin')->where('guard_name', $guard)->exists()) {
            $superAdmin->assignRole('Super Admin');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval('users_id_seq', (SELECT MAX(id) FROM users));");
        }

        $roles = ['Support Specialist', 'Billing Admin', 'Security Auditor'];

        $existingRoles = Role::whereIn('name', $roles)->where('guard_name', $guard)->pluck('name')->toArray();

        if (empty($existingRoles)) {
            $this->command->warn("⚠️ No operational roles found. Skipping staff role assignment.");
            User::factory(10)->create(['password' => Hash::make('password')]);
        } else {
            $testAccounts = [
                ['name' => 'Alice Auditor', 'email' => 'auditor@hive.os', 'role' => 'Security Auditor'],
                ['name' => 'Bob Support', 'email' => 'support@hive.os', 'role' => 'Support Specialist'],
            ];

            foreach ($testAccounts as $acc) {
                $u = User::updateOrCreate(
                    ['email' => $acc['email']],
                    ['name' => $acc['name'], 'password' => Hash::make('password'), 'email_verified_at' => now()]
                );
                if (in_array($acc['role'], $existingRoles)) {
                    $u->assignRole($acc['role']);
                }
            }

            User::factory(8)->create(['password' => Hash::make('password')])->each(function ($user) use ($existingRoles) {
                $user->assignRole($existingRoles[array_rand($existingRoles)]);
            });
        }

        $this->command->info("✅ Central Hive users & operational staff initialized.");
    }
}
