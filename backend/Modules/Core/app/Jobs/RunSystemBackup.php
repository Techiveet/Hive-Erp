<?php

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Core\Models\SystemAlert;
use Modules\Core\Support\SystemBackupCatalog;
use Throwable;

class RunSystemBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(
        protected $user = null,
        protected string $tenantId = 'central',
        protected string $type = 'all',
        protected string $trigger = 'manual'
    ) {
        $this->onQueue('system-backups');
    }

    public function handle(): void
    {
        $lock = Cache::lock('system-backups:running', $this->timeout);

        if (! $lock->get()) {
            $message = 'A system backup is already running. Wait for the active snapshot to finish before starting another one.';

            $this->logActivity($message);
            $this->createAlert('Backup Skipped', $message, 'warning');

            throw new \RuntimeException($message);
        }

        try {
            $options = [
                '--disable-notifications' => true,
                '--destination-path' => SystemBackupCatalog::destinationPath(),
                '--filename' => SystemBackupCatalog::buildFilename($this->type, $this->trigger),
            ];

            if ($this->type === 'db') {
                $options['--only-db'] = true;
            } elseif ($this->type === 'files') {
                $options['--only-files'] = true;
            }

            $exitCode = Artisan::call('backup:run', $options);

            if ($exitCode !== 0) {
                $output = trim(Artisan::output());

                throw new \RuntimeException($output !== '' ? $output : 'The backup command exited with a non-zero status.');
            }

            $description = sprintf(
                '%s %s backup completed successfully and is now available in the backup ledger.',
                $this->triggerLabel(),
                $this->typeLabel()
            );

            $this->logActivity(Str::ucfirst($description));
            $this->createAlert('Backup Completed', Str::ucfirst($description), 'info');
            $this->sendEmailNotification(
                'HIVE.OS: Backup Completed Successfully',
                "Hello {$this->user?->name},\n\nYour {$this->triggerLabel()} {$this->typeLabel()} backup completed successfully and is now available in the backup ledger.\n\nRegards,\nHIVE.OS System"
            );
        } catch (Throwable $e) {
            Log::error('Backup job failed: '.$e->getMessage(), [
                'type' => $this->type,
                'trigger' => $this->trigger,
            ]);

            $summary = Str::limit($e->getMessage(), 180);

            $this->logActivity("{$this->triggerLabel()} {$this->typeLabel()} backup failed: {$summary}");
            $this->createAlert('Backup Failed', "The {$this->triggerLabel()} {$this->typeLabel()} backup failed: {$summary}", 'critical');
            $this->sendEmailNotification(
                'HIVE.OS: Backup Failed',
                "Hello {$this->user?->name},\n\nYour {$this->triggerLabel()} {$this->typeLabel()} backup failed.\n\nError details: {$e->getMessage()}\n\nPlease review the system logs and try again."
            );

            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function displayName(): string
    {
        return sprintf('System Backup [%s/%s]', Str::upper($this->trigger), Str::upper($this->type));
    }

    public function tags(): array
    {
        return [
            'system-backups',
            $this->trigger,
            $this->type,
            $this->tenantId,
        ];
    }

    private function createAlert(string $title, string $description, string $level): void
    {
        SystemAlert::create([
            'tenant_id' => $this->tenantId === 'central' ? null : $this->tenantId,
            'title' => $title,
            'description' => $description,
            'level' => $level,
        ]);
    }

    private function logActivity(string $description): void
    {
        $log = activity('System Operations')
            ->tap(function ($activity) {
                $activity->tenant_id = $this->tenantId;
            });

        if ($this->user) {
            $log->causedBy($this->user);
        }

        $log->log($description);
    }

    private function sendEmailNotification(string $subject, string $body): void
    {
        if (! $this->user || ! $this->user->email) {
            return;
        }

        Mail::raw($body, function ($message) use ($subject) {
            $message->to($this->user->email)->subject($subject);
        });
    }

    private function triggerLabel(): string
    {
        return $this->trigger === 'automatic' ? 'automated' : 'manual';
    }

    private function typeLabel(): string
    {
        return match ($this->type) {
            'db' => 'database-only',
            'files' => 'file-only',
            default => 'full-system',
        };
    }
}
