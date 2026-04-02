<?php

namespace Modules\Core\Support;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SystemBackupCatalog
{
    public const DESTINATION_PATH = 'system-backups';

    private const FILENAME_PATTERN = '/^hive-central-(manual|automatic)-(db|files|all)-(\d{4}-\d{2}-\d{2}-\d{6})\.zip$/i';

    public static function destinationPath(): string
    {
        return self::DESTINATION_PATH;
    }

    public static function buildFilename(string $type, string $trigger, ?CarbonInterface $timestamp = null): string
    {
        $normalizedType = self::normalizeType($type);
        $normalizedTrigger = self::normalizeTrigger($trigger);
        $stamp = ($timestamp ? Carbon::instance($timestamp) : now())->format('Y-m-d-His');

        return "hive-central-{$normalizedTrigger}-{$normalizedType}-{$stamp}.zip";
    }

    public static function list(Filesystem $disk): array
    {
        $files = [];

        foreach (self::allowedDirectories() as $directory) {
            if (! $disk->exists($directory)) {
                continue;
            }

            foreach ($disk->allFiles($directory) as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) !== 'zip') {
                    continue;
                }

                $files[$file] = self::toApiPayload($disk, $file);
            }
        }

        $backups = array_values($files);

        usort(
            $backups,
            fn (array $left, array $right) => strtotime($right['created_at']) <=> strtotime($left['created_at'])
        );

        return $backups;
    }

    public static function toApiPayload(Filesystem $disk, string $path): array
    {
        $metadata = self::parseFilename(basename($path));

        return [
            'id' => base64_encode($path),
            'name' => basename($path),
            'type' => $metadata['type'],
            'trigger' => $metadata['trigger'],
            'size' => round($disk->size($path) / 1048576, 2).' MB',
            'created_at' => Carbon::createFromTimestamp($disk->lastModified($path))->toIso8601String(),
        ];
    }

    public static function parseFilename(string $filename): array
    {
        if (preg_match(self::FILENAME_PATTERN, $filename, $matches) === 1) {
            return [
                'trigger' => $matches[1] === 'automatic' ? 'auto' : 'manual',
                'type' => self::normalizeType($matches[2]),
            ];
        }

        $lowerFilename = Str::lower($filename);

        return [
            'trigger' => Str::contains($lowerFilename, ['automatic', 'auto', 'scheduled', 'cron']) ? 'auto' : 'manual',
            'type' => Str::contains($lowerFilename, ['files', 'storage']) ? 'files' : (Str::contains($lowerFilename, ['db', 'database']) ? 'db' : 'all'),
        ];
    }

    public static function isAllowedPath(string $path): bool
    {
        $normalizedPath = trim(str_replace('\\', '/', $path), '/');

        if ($normalizedPath === '' || Str::contains($normalizedPath, ['../', '..\\'])) {
            return false;
        }

        foreach (self::allowedDirectories() as $directory) {
            $normalizedDirectory = trim(str_replace('\\', '/', $directory), '/');

            if ($normalizedPath === $normalizedDirectory || Str::startsWith($normalizedPath, $normalizedDirectory.'/')) {
                return true;
            }
        }

        return false;
    }

    public static function allowedDirectories(): array
    {
        return array_values(array_unique(array_filter([
            self::DESTINATION_PATH,
            config('backup.backup.name', 'Laravel'),
            env('APP_NAME', 'Laravel'),
            'HiveErp',
            'private/HiveErp',
            'Hive',
            'backups',
        ], fn (?string $path) => filled($path))));
    }

    private static function normalizeType(string $type): string
    {
        return in_array($type, ['db', 'files', 'all'], true) ? $type : 'all';
    }

    private static function normalizeTrigger(string $trigger): string
    {
        return $trigger === 'automatic' ? 'automatic' : 'manual';
    }
}
