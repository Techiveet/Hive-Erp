<?php

namespace Modules\Core\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

// Imports for the Global Interceptor
use Illuminate\Support\Facades\Event;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Str;
use Modules\Core\Models\SystemAlert; // Correct model path

// Import the command classes & Jobs
use Modules\Core\Console\Commands\SyncLocalizationCommand;
use Modules\Core\Console\Commands\ArchiveAuditLogs;
use Modules\Core\Console\Commands\ScoutTenantImport;
use Modules\Core\Jobs\RunSystemBackup;

class CoreServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Core';
    protected string $nameLower = 'core';

    protected array $commands = [
        SyncLocalizationCommand::class,
        ArchiveAuditLogs::class,
        ScoutTenantImport::class,
    ];

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        // =========================================================================
        // 🚨 1. GLOBAL EXCEPTION & ERROR INTERCEPTOR
        // =========================================================================
        Event::listen(function (MessageLogged $event) {

            if (in_array($event->level, ['error', 'critical', 'alert', 'emergency'])) {

                try {
                    $description = $event->message;

                    if (isset($event->context['exception'])) {
                        $description = $event->context['exception']->getMessage();
                    }

                    $title = 'System ' . ucfirst($event->level) . ' Exception';

                    if (Str::contains(strtolower($description), ['backup', 'pg_dump'])) {
                        $title = 'Automated Backup Failure';
                    } elseif (Str::contains(strtolower($description), ['relation', 'does not exist', 'table', 'undefined table'])) {
                        $title = 'Missing Database Table';
                    }

                    $tenantId = (function_exists('tenant') && tenant('id')) ? tenant('id') : 'CENTRAL_NODE';

                    // 🚀 FORCE THE CENTRAL DATABASE CONNECTION
                    $centralConnection = config('tenancy.database.central_connection', env('DB_CONNECTION', 'pgsql'));

                    SystemAlert::on($centralConnection)->create([
                        'title' => $title,
                        'description' => Str::limit($description, 800),
                        'level' => 'critical',
                        'tenant_id' => $tenantId,
                    ]);

                } catch (\Throwable $e) {
                    // 🚀 FIXED: Prevent the "Silent Assassin" Crash!
                    // If the logs folder doesn't exist for the tenant, create it safely.
                    $logPath = storage_path('logs/alert_failures.log');
                    $logDir = dirname($logPath);

                    if (!is_dir($logDir)) {
                        @mkdir($logDir, 0755, true); // @ suppresses errors so we don't crash Laravel
                    }

                    @file_put_contents(
                        $logPath,
                        "[" . date('Y-m-d H:i:s') . "] ALERT SAVE FAILED: " . $e->getMessage() . PHP_EOL,
                        FILE_APPEND
                    );
                }
            }
        });

        // =========================================================================
        // 🚀 2. DYNAMIC SESSION OVERRIDE
        // =========================================================================
        if (!app()->runningInConsole()) {
            try {
                $timeout = (int) get_system_setting('session_timeout_minutes', 120);
                Config::set('session.lifetime', $timeout);
            } catch (\Throwable $e) {
                Log::error("Failed to load dynamic session timeout: " . $e->getMessage());
            }
        }

        // =========================================================================
        // 📡 3. REAL-TIME BROADCASTING
        // =========================================================================
        $this->registerBroadcastChannels();

        // =========================================================================
        // ⏰ 4. SCHEDULER & OBSERVERS
        // =========================================================================
        $this->app->booted(function () {
            if ($this->app->runningInConsole()) {
                $schedule = $this->app->make(Schedule::class);
                $this->configureSchedules($schedule);
            }
        });
    }

    protected function registerBroadcastChannels(): void
    {
        $channelsPath = module_path($this->name, 'routes/channels.php');
        if (file_exists($channelsPath)) {
            require $channelsPath;
        }
    }

    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('logs:archive --days=90')
                 ->dailyAt('00:00')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/archive_engine.log'));

        try {
            $frequency = get_system_setting('backup_frequency', 'daily');
            $time      = get_system_setting('backup_time', '02:00');
            $day       = get_system_setting('backup_day', 1);

            $backupJob  = $schedule->job(new RunSystemBackup());
            $cleanupJob = $schedule->command('backup:clean')->withoutOverlapping();

            if ($frequency === 'weekly') {
                $backupJob->weeklyOn($day, $time);
                $cleanupJob->weeklyOn($day, '03:00');
            } elseif ($frequency === 'monthly') {
                $backupJob->monthlyOn($day, $time);
                $cleanupJob->monthlyOn($day, '03:00');
            } else {
                $backupJob->dailyAt($time);
                $cleanupJob->dailyAt('03:00');
            }

        } catch (\Throwable $e) {
            Log::error("Failed to load backup settings: " . $e->getMessage());
        }
    }
}