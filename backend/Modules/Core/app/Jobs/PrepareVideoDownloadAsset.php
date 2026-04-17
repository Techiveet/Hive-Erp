<?php

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Core\Jobs\Concerns\InteractsWithTenantContext;
use Modules\Core\Models\FileEntry;
use Modules\Core\Support\VideoWatermarkService;
use Stancl\Tenancy\Tenancy;
use Throwable;

class PrepareVideoDownloadAsset implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, InteractsWithTenantContext;

    public int $timeout = 3600;
    public int $tries = 2;

    public function __construct(
        public int $fileEntryId,
        public string $tenantId = 'central'
    ) {
        $this->tenantId = trim($tenantId) !== '' ? $tenantId : 'central';
        $this->onConnection((string) config('media-library.queue_connection_name', 'redis-media'));
        $this->onQueue((string) config('media-library.download_queue_name', 'media-downloads'));
    }

    public static function dispatchMarkerKey(int $fileEntryId, string $tenantId): string
    {
        return sprintf('media-dispatch:download:%s:%s', $tenantId, $fileEntryId);
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->lockKey()))
                ->expireAfter($this->timeout + 300)
                ->dontRelease(),
        ];
    }

    public function handle(VideoWatermarkService $videoWatermarkService, Tenancy $tenancy): void
    {
        try {
            $this->initializeTenantContext($tenancy, $this->tenantId);

            $fileEntry = FileEntry::query()->find($this->fileEntryId);

            if (!$fileEntry) {
                return;
            }

            $videoWatermarkService->ensureDownloadAsset($fileEntry);
        } catch (Throwable $exception) {
            Log::error('Video download asset preparation failed.', [
                'file_entry_id' => $this->fileEntryId,
                'tenant_id' => $this->tenantId,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            Cache::forget(self::dispatchMarkerKey($this->fileEntryId, $this->tenantId));
            $this->endTenantContext($tenancy);
        }
    }

    private function lockKey(): string
    {
        return sprintf('media:download:%s:%s', $this->tenantId, $this->fileEntryId);
    }
}
