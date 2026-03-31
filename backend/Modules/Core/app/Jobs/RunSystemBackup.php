<?php

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Models\SystemAlert;

class RunSystemBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $tenantId;
    protected $type;

    public function __construct($user, $tenantId, $type = 'all')
    {
        $this->user = $user;
        $this->tenantId = $tenantId;
        $this->type = $type;
    }

    public function handle()
    {
        try {
            $options = [
                '--disable-notifications' => true, // Prevents default Spatie spam
            ];

            // 🚀 Tell Spatie exactly what to backup
            if ($this->type === 'db') {
                $options['--only-db'] = true;
            } elseif ($this->type === 'files') {
                $options['--only-files'] = true;
            }

            Artisan::call('backup:run', $options);

            // 1. Log to Audit Ledger
            activity('System Operations')
                ->causedBy($this->user)
                ->tap(function($activity) { $activity->tenant_id = $this->tenantId; })
                ->log("Completed manual '{$this->type}' backup successfully.");

            // 2. Send Database Alert
            SystemAlert::create([
                'tenant_id' => $this->tenantId === 'central' ? null : $this->tenantId,
                'title' => 'Backup Completed',
                'description' => "Your manual '{$this->type}' snapshot has been securely archived and is ready for download.",
                'level' => 'info',
            ]);

            // 3. Send Email Notification
            if ($this->user && $this->user->email) {
                Mail::raw(
                    "Hello {$this->user->name},\n\nYour requested manual system backup ({$this->type}) has completed successfully and is now available for download in the Backup Ledger.\n\nRegards,\nHIVE.OS System",
                    function ($message) {
                        $message->to($this->user->email)
                                ->subject('HIVE.OS: Backup Completed Successfully');
                    }
                );
            }

        } catch (\Exception $e) {
            Log::error("Backup Job Failed: " . $e->getMessage());

            // 1. Log to Audit Ledger
            activity('System Operations')
                ->causedBy($this->user)
                ->tap(function($activity) { $activity->tenant_id = $this->tenantId; })
                ->log("Manual '{$this->type}' backup failed: " . substr($e->getMessage(), 0, 150));

            // 2. Send Database Alert
            SystemAlert::create([
                'tenant_id' => $this->tenantId === 'central' ? null : $this->tenantId,
                'title' => 'Backup Failed',
                'description' => "Manual '{$this->type}' backup encountered a critical error: " . substr($e->getMessage(), 0, 100),
                'level' => 'critical',
            ]);

            // 3. Send Email Notification
            if ($this->user && $this->user->email) {
                Mail::raw(
                    "Hello {$this->user->name},\n\nYour requested manual system backup ({$this->type}) failed to complete.\n\nError details: {$e->getMessage()}\n\nPlease check the system logs.",
                    function ($message) {
                        $message->to($this->user->email)
                                ->subject('HIVE.OS: Backup FAILED');
                    }
                );
            }
        }
    }
}
