<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Models\Activity;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ArchiveAuditLogs extends Command
{
    protected $signature = 'logs:archive {--days=90 : The number of days to retain logs in the live database}';
    protected $description = 'Extracts old audit logs to cold storage (CSV) and purges them from the live DB.';

    public function handle()
    {
        $days = $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("Initiating Archival for logs older than {$cutoffDate->toDateString()}...");

        $query = Activity::where('created_at', '<', $cutoffDate);
        $count = $query->count();

        if ($count === 0) {
            $this->info("No logs require archiving at this time.");
            return;
        }

        $filename = 'audit_archives/archive_' . now()->format('Y_m_d_His') . '.csv';
        $file = fopen(storage_path('app/' . $filename), 'w');

        // Write CSV Headers
        fputcsv($file, ['ID', 'Log Name', 'Description', 'Event', 'Tenant ID', 'Causer ID', 'Properties', 'Created At']);

        // Chunking prevents memory crashes on massive databases
        $query->chunkById(1000, function ($logs) use ($file) {
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id, $log->log_name, $log->description, $log->event,
                    $log->tenant_id, $log->causer_id, $log->properties, $log->created_at
                ]);
            }
        });

        fclose($file);

        // 🚀 Safely delete the archived records using raw DB queries to bypass the WORM protection in our model
        DB::table(config('activitylog.table_name'))
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        $this->info("✅ Successfully archived {$count} logs to {$filename} and purged them from the live database.");
    }
}
