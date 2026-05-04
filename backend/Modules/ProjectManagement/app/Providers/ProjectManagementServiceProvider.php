<?php

namespace Modules\ProjectManagement\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class ProjectManagementServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'ProjectManagement';
    protected string $nameLower = 'projectmanagement';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();
        \Modules\ProjectManagement\Models\Task::observe(\Modules\ProjectManagement\Observers\TaskObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\ProjectManagement\Console\CheckOverdueTasks::class,
            ]);
        }
    }

    public function register(): void
    {
        parent::register();
    }
}
