<?php

namespace Modules\NightClub\Database\Seeders;

use Illuminate\Database\Seeder;

class NightClubDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            NightClubRolesSeeder::class,
        ]);
    }
}
