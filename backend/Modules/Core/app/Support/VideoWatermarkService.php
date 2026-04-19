<?php

namespace Modules\Core\Support;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Models\FileEntry;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\Process\Process;

class VideoWatermarkService
{
    private const ASSET_SIGNATURE_KEY = 'download_watermark_signature';
    private const LEGACY_SIGNATURE_KEY = 'download_watermark_title_hash';
    private const ASSET_VERSION = 'v4-brand-overlay';

    public function __construct(
        private readonly BrandSettingsStore $brandSettingsStore,
        private readonly TenantMediaStorage $mediaStorage
    ) {}

    public function ensureDownloadAsset(FileEntry $fileEntry): ?array
    {
        $media = $fileEntry->getFirstMedia('file');

        $isVideo = str_starts_with((string) $media->mime_type, 'video/');
        $isAudio = str_starts_with((string) $media->mime_type, 'audio/');

        if (!$media || (!$isVideo && !$isAudio)) {
            return null;
        }

        $watermarkText = $this->resolveWatermarkText();
        if ($watermarkText === null) {
            if (
                $fileEntry->watermarked_path !== null
                || $media->getCustomProperty(self::ASSET_SIGNATURE_KEY)
                || $media->getCustomProperty(self::LEGACY_SIGNATURE_KEY)
            ) {
                $this->invalidateDownloadAsset($fileEntry);
            }

            return null;
        }

        $lock = Cache::lock(
            $this->buildAssetLockKey($fileEntry),
            max(120, ((int) config('media-library.ffmpeg_timeout', 900)) + 60)
        );

        try {
            $lock->block(20);

            $lockedEntry = $fileEntry->fresh() ?: $fileEntry;
            $lockedMedia = $lockedEntry->getFirstMedia('file');

            $isVideo = str_starts_with((string) $lockedMedia->mime_type, 'video/');
            $isAudio = str_starts_with((string) $lockedMedia->mime_type, 'audio/');

            if (!$lockedMedia || (!$isVideo && !$isAudio)) {
                return null;
            }

            return $this->generateOrReuseDownloadAsset($lockedEntry, $lockedMedia, $watermarkText);
        } catch (LockTimeoutException) {
            Log::warning('[VideoWatermark] Timed out waiting for asset generation lock.', [
                'file_entry_id' => $fileEntry->id,
            ]);

            $freshEntry = $fileEntry->fresh() ?: $fileEntry;
            $freshMedia = $freshEntry->getFirstMedia('file');

            return $freshMedia
                ? $this->resolveExistingAsset(
                    $freshEntry,
                    $freshMedia,
                    $this->buildWatermarkSignature($watermarkText)
                )
                : null;
        } finally {
            rescue(static fn () => $lock->release(), report: false);
        }
    }

    public function resolveDownloadAsset(FileEntry $fileEntry): ?array
    {
        $media = $fileEntry->getFirstMedia('file');

        $isVideo = str_starts_with((string) $media->mime_type, 'video/');
        $isAudio = str_starts_with((string) $media->mime_type, 'audio/');

        if (!$media || (!$isVideo && !$isAudio)) {
            return null;
        }

        $watermarkText = $this->resolveWatermarkText();
        if ($watermarkText === null) {
            return null;
        }

        return $this->resolveExistingAsset(
            $fileEntry,
            $media,
            $this->buildWatermarkSignature($watermarkText)
        );
    }

    public function shouldBrandDownloads(): bool
    {
        return $this->resolveWatermarkText() !== null;
    }

    public function invalidateDownloadAsset(FileEntry $fileEntry): void
    {
        $media = $fileEntry->getFirstMedia('file');
        $disk = $media ? $this->mediaStorage->mediaDisk($media) : (config('media-library.disk_name') ?: 'public');

        $this->mediaStorage->deleteIfExists($disk, $fileEntry->watermarked_path);

        if ($media) {
            $media->setCustomProperty(self::ASSET_SIGNATURE_KEY, null);
            $media->setCustomProperty(self::LEGACY_SIGNATURE_KEY, null);
            $media->save();
        }

        if ($fileEntry->watermarked_path !== null) {
            $fileEntry->forceFill([
                'watermarked_path' => null,
            ])->save();
        }
    }

    private function generateOrReuseDownloadAsset(FileEntry $fileEntry, Media $media, string $watermarkText): ?array
    {
        $disk = $this->mediaStorage->mediaDisk($media);
        $signature = $this->buildWatermarkSignature($watermarkText);

        if ($existingAsset = $this->resolveExistingAsset($fileEntry, $media, $signature)) {
            return $existingAsset;
        }

        if (str_starts_with((string) $media->mime_type, 'audio/')) {
            Cache::put("download_progress_{$fileEntry->id}", 100, now()->addMinutes(10));

            return [
                'disk' => $disk,
                'relative_path' => $this->mediaStorage->mediaRelativePath($media),
                'filename' => $media->file_name,
            ];
        }

        $ffmpegBinary = $this->resolveFfmpegBinary();
        if ($ffmpegBinary === null) {
            Log::warning('[VideoWatermark] Missing FFmpeg binary; serving original asset.', [
                'file_entry_id' => $fileEntry->id,
            ]);

            return null;
        }

        $relativePath = $fileEntry->watermarked_path ?: $this->defaultOutputPath($media);
        $sourceLocalPath = $this->mediaStorage->stageMediaToLocalTemp($media);
        $temporaryOutputPath = $this->mediaStorage->temporaryLocalPath('mp4', 'wm-');
        $fontPath = $this->resolveFontPath();
        $watermarkOverlayPath = $this->createWatermarkOverlay($watermarkText, $fontPath);

        try {
            $command = $this->buildFfmpegCommand(
                $ffmpegBinary,
                $sourceLocalPath,
                $temporaryOutputPath,
                $watermarkText,
                $fontPath,
                $watermarkOverlayPath
            );

            $duration = $this->getVideoDuration($sourceLocalPath);
            $process = new Process($command);
            $process->setTimeout((int) config('media-library.ffmpeg_timeout', 900));

            Cache::put("download_progress_{$fileEntry->id}", 5, now()->addMinutes(15));

            $process->run(function ($type, $buffer) use ($fileEntry, $duration) {
                if ($type === Process::OUT || $type === Process::ERR) {
                    if ($duration > 0 && preg_match('/time=([0-9:.]+)/', $buffer, $matches)) {
                        $currentTime = $this->parseFfmpegTime($matches[1]);
                        $percentage = min(99, round(($currentTime / $duration) * 100));
                        Cache::put("download_progress_{$fileEntry->id}", $percentage, now()->addMinutes(15));
                    }
                }
            });

            if (!$process->isSuccessful()) {
                throw new \RuntimeException('FFmpeg failed: ' . $process->getErrorOutput());
            }
        } catch (\Throwable $exception) {
            Cache::forget("download_progress_{$fileEntry->id}");

            Log::warning('[VideoWatermark] Missing watermark overlay prerequisites; serving original asset.', [
                'file_entry_id' => $fileEntry->id,
                'error' => $exception->getMessage(),
            ]);

            $this->cleanupTemporaryArtifacts($sourceLocalPath, $temporaryOutputPath, $watermarkOverlayPath);

            return null;
        }

        if (!is_file($temporaryOutputPath) || filesize($temporaryOutputPath) < 1024) {
            Cache::forget("download_progress_{$fileEntry->id}");

            Log::error('[VideoWatermark] Generated asset is empty or incomplete.', [
                'file_entry_id' => $fileEntry->id,
                'path' => $temporaryOutputPath,
            ]);

            if (is_file($temporaryOutputPath)) {
                @unlink($temporaryOutputPath);
            }

            $this->cleanupTemporaryArtifacts($sourceLocalPath, $watermarkOverlayPath);

            return null;
        }

        try {
            $this->mediaStorage->putLocalFile($disk, $relativePath, $temporaryOutputPath);
        } catch (\Throwable $exception) {
            Cache::forget("download_progress_{$fileEntry->id}");

            Log::error('[VideoWatermark] Failed to finalize generated asset.', [
                'file_entry_id' => $fileEntry->id,
                'error' => $exception->getMessage(),
            ]);

            $this->cleanupTemporaryArtifacts($temporaryOutputPath, $sourceLocalPath, $watermarkOverlayPath);

            return null;
        }

        $this->cleanupTemporaryArtifacts($temporaryOutputPath, $sourceLocalPath, $watermarkOverlayPath);

        $fileEntry->forceFill([
            'watermarked_path' => $relativePath,
        ])->save();

        $media->setCustomProperty(self::ASSET_SIGNATURE_KEY, $signature);
        $media->setCustomProperty(self::LEGACY_SIGNATURE_KEY, null);
        $media->save();

        Cache::put("download_progress_{$fileEntry->id}", 100, now()->addMinutes(10));

        return [
            'disk' => $disk,
            'relative_path' => $relativePath,
            'filename' => $this->buildDownloadFilename($media),
        ];
    }

    private function resolveExistingAsset(FileEntry $fileEntry, Media $media, string $signature): ?array
    {
        $disk = $this->mediaStorage->mediaDisk($media);
        $relativePath = $fileEntry->watermarked_path ?: $this->defaultOutputPath($media);
        $storedSignature = (string) (
            $media->getCustomProperty(self::ASSET_SIGNATURE_KEY)
            ?: $media->getCustomProperty(self::LEGACY_SIGNATURE_KEY)
            ?: ''
        );

        if (
            $relativePath !== ''
            && Storage::disk($disk)->exists($relativePath)
            && $storedSignature !== ''
            && hash_equals($signature, $storedSignature)
        ) {
            return [
                'disk' => $disk,
                'relative_path' => $relativePath,
                'filename' => $this->buildDownloadFilename($media),
            ];
        }

        return null;
    }

    private function resolveWatermarkText(): ?string
    {
        if ($this->brandSettingsStore->shouldHideWatermark()) {
            return null;
        }

        return trim($this->brandSettingsStore->getAppTitle()) ?: 'HIVE.OS';
    }

    private function defaultOutputPath(Media $media): string
    {
        return dirname($media->getPathRelativeToRoot()) . '/processed/download_wm.mp4';
    }

    private function resolveFfmpegBinary(): ?string
    {
        $configuredPath = trim((string) config('media-library.ffmpeg_path', ''));

        if ($configuredPath !== '' && (is_file($configuredPath) || basename($configuredPath) === $configuredPath)) {
            return $configuredPath;
        }

        foreach (['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', 'ffmpeg'] as $candidate) {
            if ($candidate === 'ffmpeg' || is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveFontPath(): ?string
    {
        $candidates = [
            base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'),
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function buildFilterGraph(string $watermarkText, string $fontPath): string
    {
        $safeTitle = $this->escapeDrawText($watermarkText);
        $safeFontPath = $this->escapeDrawText($fontPath);

        return "drawtext=fontfile='{$safeFontPath}':text='{$safeTitle}':fontsize=14:fontcolor=white@0.7:box=1:boxcolor=black@0.3:boxborderw=6:x=w-tw-10:y=h-th-10:shadowx=1:shadowy=1:shadowcolor=black@0.6";
    }

    private function buildOverlayFilterGraph(): string
    {
        return '[0:v:0][1:v:0]overlay=x=main_w-overlay_w-10:y=main_h-overlay_h-10:format=auto:eof_action=repeat[branded]';
    }

    private function buildFfmpegCommand(
        string $ffmpegBinary,
        string $sourcePath,
        string $temporaryOutputPath,
        string $watermarkText,
        ?string $fontPath,
        ?string $watermarkOverlayPath
    ): array {
        $videoMap = '0:v:0';
        $command = [
            $ffmpegBinary,
            '-y',
            '-i',
            $sourcePath,
        ];

        if ($watermarkOverlayPath !== null) {
            array_push(
                $command,
                '-loop',
                '1',
                '-i',
                $watermarkOverlayPath,
                '-filter_complex',
                $this->buildOverlayFilterGraph(),
                '-shortest'
            );
            $videoMap = '[branded]';
        } elseif ($fontPath !== null) {
            array_push(
                $command,
                '-vf',
                $this->buildFilterGraph($watermarkText, $fontPath)
            );
        } else {
            throw new \RuntimeException('Unable to build video watermark: no overlay or font is available.');
        }

        return array_merge($command, [
            '-c:a',
            'copy',
            '-map',
            $videoMap,
            '-map',
            '0:a?',
            '-sn',
            '-dn',
            '-map_metadata',
            '-1',
            '-movflags',
            '+faststart',
            '-max_muxing_queue_size',
            '1024',
            '-pix_fmt',
            'yuv420p',
            '-c:v',
            'libx264',
            '-preset',
            'ultrafast',
            '-profile:v',
            'main',
            '-level',
            '4.1',
            '-crf',
            '23',
            '-f',
            'mp4',
            $temporaryOutputPath,
        ]);
    }

    private function createWatermarkOverlay(string $watermarkText, ?string $fontPath): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        $normalizedText = preg_replace('/\s+/u', ' ', trim($watermarkText)) ?: 'HIVE.OS';
        $normalizedText = Str::limit($normalizedText, 60, '');
        $fontSize = 12;
        $paddingX = 12;
        $paddingY = 8;
        $canvasHeight = 42;
        $maxWidth = 320;
        $useTtf = $fontPath !== null && function_exists('imagettfbbox') && function_exists('imagettftext');

        if ($useTtf) {
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $normalizedText);
            $textWidth = (int) max(
                abs(($bbox[2] ?? 0) - ($bbox[0] ?? 0)),
                abs(($bbox[4] ?? 0) - ($bbox[6] ?? 0))
            );
            $textHeight = (int) max(
                abs(($bbox[7] ?? 0) - ($bbox[1] ?? 0)),
                abs(($bbox[5] ?? 0) - ($bbox[3] ?? 0))
            );
        } else {
            $font = 5;
            $characterCount = function_exists('mb_strlen') ? mb_strlen($normalizedText) : strlen($normalizedText);
            $textWidth = imagefontwidth($font) * $characterCount;
            $textHeight = imagefontheight($font);
        }

        $canvasWidth = max(220, min($maxWidth, $textWidth + ($paddingX * 2)));
        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagealphablending($canvas, true);

        $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 102);
        $background = imagecolorallocatealpha($canvas, 8, 15, 27, 76);
        $outline = imagecolorallocatealpha($canvas, 255, 255, 255, 104);
        $textColor = imagecolorallocatealpha($canvas, 255, 255, 255, 18);

        imagefilledrectangle($canvas, 4, 10, $canvasWidth - 1, $canvasHeight - 5, $shadow);
        imagefilledrectangle($canvas, 0, 0, $canvasWidth - 6, $canvasHeight - 16, $background);
        imagerectangle($canvas, 0, 0, $canvasWidth - 6, $canvasHeight - 16, $outline);

        if ($useTtf) {
            $baseline = (int) round(($canvasHeight + $textHeight) / 2) - 8;
            imagettftext($canvas, $fontSize, 0, $paddingX, $baseline, $textColor, $fontPath, $normalizedText);
        } else {
            $font = 5;
            $textX = max($paddingX, (int) floor(($canvasWidth - $textWidth) / 2));
            $textY = (int) floor(($canvasHeight - imagefontheight($font)) / 2) - 6;
            imagestring($canvas, $font, $textX, max(0, $textY), $normalizedText, $textColor);
        }

        $directory = storage_path('app/temp/video-watermarks');
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $path = $directory . '/' . sha1(self::ASSET_VERSION . '|' . $normalizedText . '|' . Str::random(8)) . '.png';
        imagepng($canvas, $path, 9);
        imagedestroy($canvas);

        return is_file($path) ? $path : null;
    }

    private function escapeDrawText(string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($value)) ?: 'Untitled Video';
        $limited = Str::limit($normalized, 90, '');

        return str_replace(
            ['\\', ':', "'", ',', '[', ']', '%'],
            ['\\\\', '\:', "\\'", '\,', '\[', '\]', '\%'],
            $limited
        );
    }

    private function buildDownloadFilename(Media $media): string
    {
        $baseName = pathinfo((string) $media->file_name, PATHINFO_FILENAME);
        $sanitized = preg_replace('/[^A-Za-z0-9 _.-]+/', '', $baseName) ?: 'video-download';
        $sanitized = trim((string) $sanitized);
        $sanitized = $sanitized === '' ? 'video-download' : $sanitized;

        return Str::limit($sanitized, 90, '') . '.mp4';
    }

    private function buildWatermarkSignature(string $watermarkText): string
    {
        return sha1(self::ASSET_VERSION . '|' . $watermarkText);
    }

    private function buildAssetLockKey(FileEntry $fileEntry): string
    {
        $context = function_exists('tenant') && tenant('id')
            ? 'tenant:' . tenant('id')
            : 'central';

        return "video_download_asset:{$context}:{$fileEntry->id}";
    }

    private function getVideoDuration(string $path): float
    {
        $ffprobe = str_replace('ffmpeg', 'ffprobe', $this->resolveFfmpegBinary() ?: 'ffmpeg');

        $process = new Process([
            $ffprobe,
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $path,
        ]);

        $process->run();

        return (float) trim($process->getOutput());
    }

    private function parseFfmpegTime(string $time): float
    {
        $parts = array_reverse(explode(':', $time));
        $seconds = (float) array_shift($parts);

        if (isset($parts[0])) {
            $seconds += (int) $parts[0] * 60;
        }

        if (isset($parts[1])) {
            $seconds += (int) $parts[1] * 3600;
        }

        return $seconds;
    }

    private function cleanupTemporaryArtifacts(?string ...$paths): void
    {
        foreach ($paths as $path) {
            if ($path && is_file($path)) {
                @unlink($path);
            }
        }
    }
}
