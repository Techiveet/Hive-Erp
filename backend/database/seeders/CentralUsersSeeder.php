<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class CentralUsersSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Overlord
        $superAdmin = User::updateOrCreate(
            ['email' => 'super@hive.os'],
            [
                'name' => 'Hive Overlord',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Explicitly set guard for Spatie in Octane
        $superAdmin->guard_name = 'web';
        $superAdmin->assignRole('Super Admin');

        // 2. Postgres Sequence Reset (Critical for IDs)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval('users_id_seq', (SELECT MAX(id) FROM users));");
        }

        // 3. Staff Generation
        $staffRole = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
        User::factory(10)->create([
            'password' => Hash::make('password'),
        ])->each(function ($user) use ($staffRole) {
            $user->guard_name = 'web';
            $user->assignRole($staffRole);
        });

        $this->command->info("✅ Central Hive users initialized.");
    }
}
