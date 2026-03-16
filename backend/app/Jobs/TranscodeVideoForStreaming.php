<?php

namespace App\Jobs;

use App\Models\FileEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use FFMpeg\Format\Video\X264;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TranscodeVideoForStreaming implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

   public $timeout = 3600;
    public $tries = 1;

    public function __construct(public FileEntry $fileEntry) {}

    public function handle()
    {
        try {
            $media = $this->fileEntry->getFirstMedia('file');
            if (!$media || !str_starts_with($media->mime_type, 'video/')) return;

            $disk = $media->disk;
            $sourcePath = $media->getPathRelativeToRoot();
            $streamDir = dirname($sourcePath) . '/processed';

            // Ensure the directory exists on the disk
            Storage::disk($disk)->makeDirectory($streamDir);

            $playlistName = $streamDir . '/playlist.m3u8';
            $watermarkedName = $streamDir . '/download_wm.mp4';

            // Base format for the download
            $format = (new X264)->setKiloBitrate(2500)->setAudioCodec('aac');

            // 🔥 FIX: Use absolute path for local watermark file so FFmpeg can find it natively
            $watermarkPath = storage_path('app/public/watermark.png');

            // Start FFmpeg process
            $ffmpeg = FFMpeg::fromDisk($disk)->open($sourcePath);

            // 1. Generate Watermarked MP4 for Download
            $ffmpeg->export()
                ->toDisk($disk)
                ->inFormat($format)
                ->addFilter(function ($filters) use ($watermarkPath) {
                    if (file_exists($watermarkPath)) {
                        $filters->watermark($watermarkPath, [
                            'position' => 'relative',
                            'bottom' => 25,
                            'right' => 25,
                        ]);
                    }
                })
                ->save($watermarkedName);

            // 2. Generate HLS for Streaming (Usually kept clean without burn-in, relies on UI overlay)
            $ffmpeg->exportForHLS()
                ->addFormat((new X264)->setKiloBitrate(1500))
                ->toDisk($disk)
                ->save($playlistName);

            // Update Database
            $this->fileEntry->update([
                'hls_path' => $playlistName,
                'watermarked_path' => $watermarkedName
            ]);

        } catch (\Exception $e) {
            Log::error("FFmpeg Job Failed: " . $e->getMessage());
            throw $e;
        }
    }
}
