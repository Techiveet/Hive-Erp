<?php

namespace Modules\Tenancy\Providers;

use Modules\Tenancy\Console\Commands\ProvisionTenantCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class TenancyServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Tenancy';

    protected string $nameLower = 'tenancy';

    protected array $commands = [
        ProvisionTenantCommand::class,
    ];

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
