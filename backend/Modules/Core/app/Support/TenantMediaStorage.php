<?php

namespace Modules\Core\Support;

use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TenantMediaStorage
{
    public function mediaDisk(?Media $media = null): string
    {
        $disk = $media?->disk
            ?: config('media-library.disk_name')
            ?: config('filesystems.default', 'public');

        $resolved = trim((string) $disk);

        return $resolved !== '' ? $resolved : 'public';
    }

    /**
     * @param  array<int, string>  $preferredConversions
     */
    public function mediaRelativePath(Media $media, array $preferredConversions = []): string
    {
        $path = $media->getAvailablePathRelativeToRoot($preferredConversions);
        $normalized = ltrim(str_replace('\\', '/', trim((string) $path)), '/');

        if ($normalized === '') {
            throw new RuntimeException('Unable to resolve media path on storage disk.');
        }

        return $normalized;
    }

    public function stageMediaToLocalTemp(Media $media): string
    {
        $extension = pathinfo((string) $media->file_name, PATHINFO_EXTENSION);

        return $this->stageDiskObjectToLocalTemp(
            $this->mediaDisk($media),
            $this->mediaRelativePath($media),
            $extension !== '' ? $extension : null
        );
    }

    public function stageDiskObjectToLocalTemp(string $disk, string $relativePath, ?string $extension = null): string
    {
        $stream = $this->openReadStream($disk, $relativePath);
        $localPath = $this->temporaryLocalPath($extension);
        $target = fopen($localPath, 'wb');

        if (!is_resource($target)) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            throw new RuntimeException('Unable to open temporary media file for writing.');
        }

        stream_copy_to_stream($stream, $target);
        fclose($stream);
        fclose($target);

        return $localPath;
    }

    public function temporaryLocalPath(?string $extension = null, string $prefix = 'media-'): string
    {
        $directory = storage_path('app/temp/media-staging');

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $path = tempnam($directory, $prefix);

        if ($path === false) {
            throw new RuntimeException('Unable to allocate temporary media path.');
        }

        if ($extension === null || trim($extension) === '') {
            return $path;
        }

        $withExtension = $path . '.' . ltrim(trim($extension), '.');

        if (!@rename($path, $withExtension)) {
            @unlink($path);
            throw new RuntimeException('Unable to finalize temporary media path.');
        }

        return $withExtension;
    }

    public function putLocalFile(string $disk, string $relativePath, string $localPath): void
    {
        $directory = dirname($relativePath);

        if ($directory !== '' && $directory !== '.') {
            Storage::disk($disk)->makeDirectory($directory);
        }

        $stream = fopen($localPath, 'rb');

        if (!is_resource($stream)) {
            throw new RuntimeException('Unable to open local media artifact for upload.');
        }

        try {
            Storage::disk($disk)->put($relativePath, $stream);
        } finally {
            fclose($stream);
        }
    }

    public function deleteIfExists(string $disk, ?string $relativePath): void
    {
        $normalized = trim((string) $relativePath);

        if ($normalized !== '' && Storage::disk($disk)->exists($normalized)) {
            Storage::disk($disk)->delete($normalized);
        }
    }

    public function streamResponse(string $disk, string $relativePath, array $headers = [], int $status = 200): StreamedResponse
    {
        $stream = $this->openReadStream($disk, $relativePath);

        return response()->stream(function () use ($stream): void {
            while (!feof($stream)) {
                echo fread($stream, 8192);
                flush();
            }

            fclose($stream);
        }, $status, $headers);
    }

    public function temporaryUrl(string $disk, string $relativePath, DateTimeInterface $expiresAt, array $options = []): ?string
    {
        try {
            return Storage::disk($disk)->temporaryUrl($relativePath, $expiresAt, $options);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return resource
     */
    public function openReadStream(string $disk, string $relativePath)
    {
        $stream = Storage::disk($disk)->readStream($relativePath);

        if (!is_resource($stream)) {
            throw new RuntimeException(sprintf('Unable to open media stream for [%s] on disk [%s].', $relativePath, $disk));
        }

        return $stream;
    }

    public function sanitizeInlineFilename(string $filename, string $fallback = 'media-stream.bin'): string
    {
        $safeFilename = basename($filename);
        $safeFilename = preg_replace('/[^A-Za-z0-9._ -]+/', '', $safeFilename) ?: $fallback;

        return trim($safeFilename) !== '' ? trim($safeFilename) : $fallback;
    }
}
