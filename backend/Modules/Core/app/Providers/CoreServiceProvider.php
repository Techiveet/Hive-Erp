<?php

namespace Modules\Core\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

// 🚀 Import the command classes
use Modules\Core\Console\Commands\SyncLocalizationCommand;
use Modules\Core\Console\Commands\ArchiveAuditLogs;
use Modules\Core\Console\Commands\ScoutTenantImport;

class CoreServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Core';
    protected string $nameLower = 'core';

    /**
     * Command classes to register.
     */
    protected array $commands = [
        SyncLocalizationCommand::class,
        ArchiveAuditLogs::class,
        ScoutTenantImport::class,
    ];

    /**
     * Provider classes to register.
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * 🚀 NEW: Hook into Laravel's boot lifecycle to register module schedules.
     */
    public function boot(): void
    {
        parent::boot();

        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $this->configureSchedules($schedule);
        });
    }

    /**
     * Define module schedules.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        // Automatically archive logs older than 90 days every night at midnight
        $schedule->command('logs:archive --days=90')
                 ->dailyAt('00:00')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/archive_engine.log'));
    }
}
