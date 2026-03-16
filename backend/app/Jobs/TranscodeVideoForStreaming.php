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
            \Illuminate\Support\Facades\Storage::disk($disk)->makeDirectory($streamDir);

            $playlistName = $streamDir . '/playlist.m3u8';
            $watermarkedName = $streamDir . '/download_wm.mp4';

            $watermarkPath = storage_path('app/public/watermark.png');

            // Start FFmpeg process
            $ffmpeg = \ProtoneMedia\LaravelFFMpeg\Support\FFMpeg::fromDisk($disk)->open($sourcePath);

            // 1. Generate Watermarked MP4 for direct downloading
            $ffmpeg->export()
                ->toDisk($disk)
                ->inFormat((new \FFMpeg\Format\Video\X264)->setKiloBitrate(2500)->setAudioCodec('aac'))
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
            $this->fileEntry->update([
                'hls_path' => $playlistName,
                'watermarked_path' => $watermarkedName
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("FFmpeg Job Failed: " . $e->getMessage());
            throw $e;
        }
    }
}
