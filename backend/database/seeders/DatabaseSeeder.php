<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Identity\Database\Seeders\IdentityDatabaseSeeder;
use Modules\Tenancy\Database\Seeders\TenancyDatabaseSeeder;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // 1. Establish central roles and super admins
            IdentityDatabaseSeeder::class,

            // 2. Spawn tenant databases (this triggers the tenant-specific seeders)
            TenancyDatabaseSeeder::class,

            // 3. Establish global core settings and languages
            CoreDatabaseSeeder::class,
        ]);
    }
}
