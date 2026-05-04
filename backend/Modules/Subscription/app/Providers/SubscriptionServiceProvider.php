<?php

namespace Modules\Subscription\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Modules\Subscription\Support\FeatureAccessService;
use Modules\Subscription\Console\Commands\ReconcileTenantSubscriptionsCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class SubscriptionServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Subscription';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'subscription';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        ReconcileTenantSubscriptionsCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Blade::if('subscribed', function (string $moduleSlug): bool {
            $tenant = function_exists('tenant') ? tenant() : null;

            return app(FeatureAccessService::class)->hasModule($tenant, $moduleSlug);
        });

        Blade::if('feature', function (string $featureSlug): bool {
            $tenant = function_exists('tenant') ? tenant() : null;

            return app(FeatureAccessService::class)->hasFeature($tenant, $featureSlug);
        });
    }

    /**
     * Define module schedules.
     * 
     * @param $schedule
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('subscriptions:reconcile')->hourly();
    }
}

