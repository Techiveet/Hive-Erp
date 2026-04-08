<?php

namespace Modules\Subscription\Database\Seeders;

use Illuminate\Database\Seeder;

class SubscriptionDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TenantModuleSubscriptionsSeeder::class,
        ]);
    }
}
