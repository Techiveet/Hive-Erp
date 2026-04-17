<?php

namespace Modules\Inventory\Providers;

use Modules\Inventory\Contracts\InventoryIntegrationGateway;
use Modules\Inventory\Support\EloquentInventoryIntegrationGateway;
use Nwidart\Modules\Support\ModuleServiceProvider;

class InventoryServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Inventory';

    protected string $nameLower = 'inventory';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(InventoryIntegrationGateway::class, EloquentInventoryIntegrationGateway::class);
    }
}
