<?php

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Jobs\Concerns\InteractsWithTenantContext;
use Modules\Core\Models\FileEntry;
use Modules\Core\Support\TenantMediaStorage;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Stancl\Tenancy\Tenancy;
use Throwable;

class TranscodeVideoForStreaming implements ShouldQueue
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
        $this->onQueue((string) config('media-library.queue_name', 'media-processing'));
    }

    public static function dispatchMarkerKey(int $fileEntryId, string $tenantId): string
    {
        return sprintf('media-dispatch:stream:%s:%s', $tenantId, $fileEntryId);
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

    public function handle(Tenancy $tenancy): void
    {
        try {
            $this->initializeTenantContext($tenancy, $this->tenantId);

            $fileEntry = FileEntry::query()->find($this->fileEntryId);
            if (!$fileEntry) {
                return;
            }

            $media = $fileEntry->getFirstMedia('file');
            if (!$media || !str_starts_with((string) $media->mime_type, 'video/')) {
                return;
            }

            $disk = app(TenantMediaStorage::class)->mediaDisk($media);
            $sourcePath = $media->getPathRelativeToRoot();
            $streamDir = dirname($sourcePath) . '/processed';
            $playlistName = $streamDir . '/playlist.m3u8';

            if ($fileEntry->hls_path && Storage::disk($disk)->exists($fileEntry->hls_path)) {
                return;
            }

            // Ensure the directory exists on the disk
            Storage::disk($disk)->makeDirectory($streamDir);

            // Start FFmpeg process
            $ffmpeg = FFMpeg::fromDisk($disk)->open($sourcePath);

            // 2. THE YOUTUBE QUALITY LADDER (HLS Multi-Bitrate)
            // Define the bitrates (Higher resolution = needs more bitrate)
            $bitrate360p  = (new \FFMpeg\Format\Video\X264)->setKiloBitrate(800);
            $bitrate480p  = (new \FFMpeg\Format\Video\X264)->setKiloBitrate(1200);
            $bitrate720p  = (new \FFMpeg\Format\Video\X264)->setKiloBitrate(2500);
            $bitrate1080p = (new \FFMpeg\Format\Video\X264)->setKiloBitrate(4500);

            // Generate the master playlist and all chunked resolutions
            $ffmpeg->exportForHLS()
                ->toDisk($disk)
                ->addFormat($bitrate360p, function($media) {
                    $media->scale(640, 360); // 360p
                })
                ->addFormat($bitrate480p, function($media) {
                    $media->scale(854, 480); // 480p
                })
                ->addFormat($bitrate720p, function($media) {
                    $media->scale(1280, 720); // 720p HD
                })
                ->addFormat($bitrate1080p, function($media) {
                    $media->scale(1920, 1080); // 1080p Full HD
                })
                ->save($playlistName);

            // Update Database
            $fileEntry->update([
                'hls_path' => $playlistName,
            ]);
        } catch (Throwable $e) {
            Log::error("FFmpeg Job Failed: " . $e->getMessage(), [
                'file_entry_id' => $this->fileEntryId,
                'tenant_id' => $this->tenantId,
            ]);
            throw $e;
        } finally {
            Cache::forget(self::dispatchMarkerKey($this->fileEntryId, $this->tenantId));
            $this->endTenantContext($tenancy);
        }
    }

    private function lockKey(): string
    {
        return sprintf('media:stream:%s:%s', $this->tenantId, $this->fileEntryId);
    }
}
