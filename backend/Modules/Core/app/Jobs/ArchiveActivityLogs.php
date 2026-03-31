<?php

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ArchiveActivityLogs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $days;

    public function __construct($days = 90) {
        $this->days = $days;
    }

    public function handle() {
        $cutoffDate = Carbon::now()->subDays($this->days);

        try {
            // 🚀 THE FIX: Chain ->connection('central') before every DB operation
            DB::connection('central')->transaction(function () use ($cutoffDate) {

                // 1. Copy old logs to archive table using explicit casting for morph IDs
                DB::connection('central')->statement("
                    INSERT INTO activity_log_archives
                    (log_name, description, subject_type, subject_id, causer_type, causer_id, properties, batch_uuid, event, tenant_id, created_at, updated_at)
                    SELECT
                        log_name,
                        description,
                        subject_type,
                        CAST(subject_id AS VARCHAR),
                        causer_type,
                        CAST(causer_id AS VARCHAR),
                        properties,
                        batch_uuid,
                        event,
                        tenant_id,
                        created_at,
                        updated_at
                    FROM activity_log
                    WHERE created_at < ?", [$cutoffDate]
                );

                // 2. Delete from main table
                DB::connection('central')->table('activity_log')->where('created_at', '<', $cutoffDate)->delete();
            });

            Log::info("Successfully archived activity logs older than {$this->days} days.");

        } catch (\Exception $e) {
            Log::error("Archive Job Failed: " . $e->getMessage());
            throw $e;
        }
    }
}
