<?php

namespace Modules\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\User;
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
                'is_active' => true,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
            ]
        );

        if (Role::where('name', 'Super Admin')->where('guard_name', $guard)->exists()) {
            $superAdmin->syncRoles(['Super Admin']);
            $superAdmin->syncPermissions(
                Permission::where('guard_name', $guard)->pluck('name')->all()
            );
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval('users_id_seq', (SELECT MAX(id) FROM users));");
        }

        $roles = ['Support Specialist', 'Billing Admin', 'Security Auditor'];
        $existingRoles = Role::whereIn('name', $roles)->where('guard_name', $guard)->pluck('name')->toArray();

        if (empty($existingRoles)) {
            $this->command->warn('No operational roles found. Creating central users without role assignments.');

            for ($i = 1; $i <= 10; $i++) {
                User::updateOrCreate(
                    ['email' => "central-user-{$i}@hive.os"],
                    [
                        'name' => "Central User {$i}",
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                        'is_active' => true,
                        'two_factor_secret' => null,
                        'two_factor_recovery_codes' => null,
                    ]
                );
            }
        } else {
            $testAccounts = [
                ['name' => 'Alice Auditor', 'email' => 'auditor@hive.os', 'role' => 'Security Auditor'],
                ['name' => 'Bob Support', 'email' => 'support@hive.os', 'role' => 'Support Specialist'],
            ];

            foreach ($testAccounts as $acc) {
                $user = User::updateOrCreate(
                    ['email' => $acc['email']],
                    [
                        'name' => $acc['name'],
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                        'is_active' => true,
                        'two_factor_secret' => null,
                        'two_factor_recovery_codes' => null,
                    ]
                );

                if (in_array($acc['role'], $existingRoles, true)) {
                    $user->syncRoles([$acc['role']]);
                }
            }

            for ($i = 1; $i <= 8; $i++) {
                $user = User::updateOrCreate(
                    ['email' => "ops-user-{$i}@hive.os"],
                    [
                        'name' => 'Ops User '.Str::upper(Str::random(4)),
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                        'is_active' => true,
                        'two_factor_secret' => null,
                        'two_factor_recovery_codes' => null,
                    ]
                );

                $user->syncRoles([$existingRoles[array_rand($existingRoles)]]);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('Central Hive users and operational staff initialized.');
    }
}
