<?php

namespace Modules\Core\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config; // 🚀 ADDED CONFIG FACADE

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
     * 🚀 Hook into Laravel's boot lifecycle.
     */
    public function boot(): void
    {
        parent::boot();

        // =========================================================================
        // 🚀 DYNAMIC SESSION OVERRIDE
        // Overwrite Laravel's .env session lifetime with our global database setting
        // =========================================================================
        if (!app()->runningInConsole()) {
            try {
                // Fetch the setting (Fallback to 120 if not set)
                $timeout = (int) get_system_setting('session_timeout_minutes', 120);

                // Force Laravel's native session engine to obey our database setting
                Config::set('session.lifetime', $timeout);
            } catch (\Exception $e) {
                // 🛡️ Silently fail during early database setup/migrations
                // If the 'settings' table doesn't exist yet, it won't crash the app
            }
        }

        // =========================================================================
        // 📡 REAL-TIME BROADCASTING (REVERB)
        // Load the modular channels.php file for WebSocket authorization
        // =========================================================================
        $this->registerBroadcastChannels();

        // =========================================================================
        // ⏰ SCHEDULER & OBSERVERS
        // =========================================================================
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $this->configureSchedules($schedule);
        });

        // Register the Activity Observer for real-time dashboard updates
        \Modules\Core\Models\Activity::observe(\Modules\Core\Observers\ActivityObserver::class);
    }

    /**
     * 🚀 Load the broadcast channels securely for this module.
     */
    protected function registerBroadcastChannels(): void
    {
        // Dynamically resolve the path using the Nwidart module helper
        $channelsPath = module_path($this->name, 'routes/channels.php');

        if (file_exists($channelsPath)) {
            require $channelsPath;
        }
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
