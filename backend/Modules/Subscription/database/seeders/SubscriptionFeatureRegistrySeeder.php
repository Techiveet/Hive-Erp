<?php

namespace Modules\Subscription\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Subscription\Support\SubscriptionRegistrySyncService;

class SubscriptionFeatureRegistrySeeder extends Seeder
{
    public function run(SubscriptionRegistrySyncService $registry): void
    {
        $result = $registry->sync();

        $this->command?->info(sprintf(
            '   -> Synced %d subscription modules, %d submodules, %d features, and %d plans.',
            count($result['modules']),
            count($result['submodules']),
            count($result['features']),
            count($result['plans'])
        ));
    }
}
