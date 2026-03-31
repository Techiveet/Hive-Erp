<?php

namespace App\Listeners;

use Stancl\Tenancy\Database\DatabaseManager;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use Stancl\Tenancy\Tenancy;

class FlushTenantContext
{
    public function handle(object $event): void
    {
        if (!app()->bound(Tenancy::class)) {
            return;
        }

        $tenancy = app(Tenancy::class);

        if ($tenancy->initialized) {
            $tenancy->end();
        }

        DomainTenantResolver::$currentDomain = null;

        app(DatabaseManager::class)->reconnectToCentral();
    }
}
