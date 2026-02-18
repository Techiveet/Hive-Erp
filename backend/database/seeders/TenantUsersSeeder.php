<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantUsersSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = tenant('id');
        $guard = 'tenant';

        // 1. Company Admin
        $admin = User::firstOrCreate(
            ['email' => "admin@{$tenantId}.com"],
            [
                'name' => ucfirst($tenantId) . ' Controller',
                'password' => Hash::make('password'),
            ]
        );
        $admin->guard_name = $guard;
        $admin->assignRole('Admin');

        // 2. Company Staff
        User::factory(5)->create([
            'password' => Hash::make('password'),
        ])->each(function ($user) use ($guard) {
            $user->guard_name = $guard;
            $user->assignRole('Employee');
        });
    }
}
