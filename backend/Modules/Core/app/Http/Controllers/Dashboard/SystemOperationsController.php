<?php

namespace Modules\Core\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Support\TemporaryDownloadSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Jobs\RunSystemBackup;
use Modules\Core\Models\Setting;
use Modules\Core\Support\SystemBackupCatalog;

class SystemOperationsController extends Controller
{
    public function __construct(
        private readonly TemporaryDownloadSignature $temporaryDownloadSignature
    ) {
    }

    private function userHasPermission($user, string $permission): bool
    {
        if (! $user) {
            return false;
        }

        $user->loadMissing(['permissions', 'roles.permissions']);

        return $user->permissions->contains('name', $permission)
            || $user->roles->flatMap->permissions->contains('name', $permission)
            || $user->roles->contains('name', 'Super Admin');
    }

    public function flushCache(Request $request): JsonResponse
    {
        Artisan::call('optimize:clear');
        $currentTenant = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';

        activity('System Operations')
            ->causedBy(auth()->user())
            ->tap(function ($activity) use ($currentTenant) {
                $activity->tenant_id = $currentTenant;
            })
            ->log('Flushed all system caches and optimized memory.');

        return response()->json(['message' => 'System cache successfully purged.']);
    }

    public function getBackupSettings(): JsonResponse
    {
        if ($response = $this->ensureCentralBackupWorkspace()) {
            return $response;
        }

        return response()->json([
            'data' => [
                'backup_frequency' => get_system_setting('backup_frequency', 'daily'),
                'backup_time' => get_system_setting('backup_time', '02:00'),
                'backup_day' => (int) get_system_setting('backup_day', 1),
            ],
        ]);
    }

    public function triggerBackup(Request $request): JsonResponse
    {
        if ($response = $this->ensureCentralBackupWorkspace()) {
            return $response;
        }

        $request->validate([
            'type' => 'required|in:db,files,all',
        ]);

        RunSystemBackup::dispatch(auth()->user(), 'central', $request->string('type')->value(), 'manual');

        activity('System Operations')
            ->causedBy(auth()->user())
            ->tap(function ($activity) {
                $activity->tenant_id = 'central';
            })
            ->log("Manual {$request->type} backup job dispatched to Horizon workers.");

        return response()->json([
            'message' => 'Backup initiated. Monitor the system alerts for completion.',
        ]);
    }

    public function updateSchedule(Request $request): JsonResponse
    {
        if ($response = $this->ensureCentralBackupWorkspace()) {
            return $response;
        }

        $request->validate([
            'frequency' => 'required|in:daily,weekly,monthly',
            'time' => 'required|date_format:H:i',
            'day' => 'required|integer|min:1|max:31',
        ]);

        if ($request->frequency === 'weekly' && $request->integer('day') > 7) {
            return response()->json([
                'message' => 'Weekly backups require a day value between 1 and 7.',
            ], 422);
        }

        foreach ([
            'backup_frequency' => $request->frequency,
            'backup_time' => $request->time,
            'backup_day' => (string) $request->day,
        ] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        clear_system_settings_cache();

        activity('System Operations')
            ->causedBy(auth()->user())
            ->tap(function ($activity) {
                $activity->tenant_id = 'central';
            })
            ->log("Automated backup schedule updated to {$request->frequency} at {$request->time}.");

        return response()->json([
            'message' => 'Automated backup schedule updated successfully.',
        ]);
    }

    public function getBackups(Request $request): JsonResponse
    {
        if ($response = $this->ensureCentralBackupWorkspace()) {
            return $response;
        }

        $disk = $this->backupDisk();

        return response()->json([
            'data' => SystemBackupCatalog::list($disk),
        ]);
    }

    public function signedBackupDownloadUrl(Request $request, string $id): JsonResponse
    {
        if ($response = $this->ensureCentralBackupWorkspace()) {
            return $response;
        }

        $path = $this->resolveBackupPath($id);
        if (! $path) {
            return response()->json(['error' => 'Invalid backup archive reference.'], 404);
        }

        $user = $request->user();
        if (! $this->userHasPermission($user, 'view_backups') && ! $this->userHasPermission($user, 'manage_backups')) {
            return response()->json(['error' => 'Forbidden. Missing backup access permission.'], 403);
        }

        $expiresAt = now()->addMinutes(5)->timestamp;
        $payload = [
            'id' => $id,
            'uid' => (int) $user->id,
            'exp' => $expiresAt,
            'scope' => 'backup_download',
        ];

        return response()->json([
            'url' => url('/api/v1/system/backups/'.$id.'/download?'.http_build_query([
                'uid' => (int) $user->id,
                'exp' => $expiresAt,
                'scope' => 'backup_download',
                'sig' => $this->temporaryDownloadSignature->sign($payload),
            ])),
            'expires_at' => $expiresAt,
        ]);
    }

    public function downloadBackup(Request $request, string $id): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($response = $this->ensureCentralBackupWorkspace()) {
            return $response;
        }

        $userId = (int) $request->query('uid', 0);
        $expiresAt = (int) $request->query('exp', 0);
        $scope = (string) $request->query('scope', '');
        $signature = (string) $request->query('sig', '');

        if ($userId <= 0 || $expiresAt <= 0 || $scope !== 'backup_download' || $signature === '') {
            return response()->json(['error' => 'Missing or invalid download authorization.'], 401);
        }

        if ($expiresAt < now()->timestamp) {
            return response()->json(['error' => 'Download URL expired.'], 401);
        }

        $payload = [
            'id' => $id,
            'uid' => $userId,
            'exp' => $expiresAt,
            'scope' => $scope,
        ];

        if (! $this->temporaryDownloadSignature->matches($payload, $signature)) {
            return response()->json(['error' => 'Invalid download signature.'], 403);
        }

        $user = \Modules\Identity\Models\User::query()->find($userId);
        if (! $user) {
            return response()->json(['error' => 'Unauthorized or expired user context.'], 401);
        }

        if (! $this->userHasPermission($user, 'view_backups') && ! $this->userHasPermission($user, 'manage_backups')) {
            return response()->json(['error' => 'Forbidden. Missing backup access permission.'], 403);
        }

        $path = $this->resolveBackupPath($id);

        if (! $path) {
            return response()->json(['error' => 'Invalid backup archive reference.'], 404);
        }

        $disk = $this->backupDisk();

        if (! $disk->exists($path)) {
            return response()->json(['error' => 'Backup file not found on disk.'], 404);
        }

        activity('System Operations')
            ->causedBy($user)
            ->tap(function ($activity) {
                $activity->tenant_id = 'central';
            })
            ->log('Downloaded system backup archive: '.basename($path));

        return response()->streamDownload(function () use ($disk, $path) {
            $stream = $disk->readStream($path);

            if (! is_resource($stream)) {
                return;
            }

            fpassthru($stream);
            fclose($stream);
        }, basename($path), [
            'Content-Type' => $disk->mimeType($path) ?: 'application/zip',
            'Content-Length' => $disk->size($path),
        ]);
    }

    public function deleteBackup(Request $request, string $id): JsonResponse
    {
        if ($response = $this->ensureCentralBackupWorkspace()) {
            return $response;
        }

        $path = $this->resolveBackupPath($id);

        if (! $path) {
            return response()->json(['message' => 'Invalid backup archive reference.'], 404);
        }

        $disk = $this->backupDisk();

        if ($disk->exists($path)) {
            $disk->delete($path);
        }

        activity('System Operations')
            ->causedBy(auth()->user())
            ->tap(function ($activity) {
                $activity->tenant_id = 'central';
            })
            ->log('Deleted system backup archive: '.basename($path));

        return response()->json(['message' => 'Backup deleted successfully.']);
    }

    private function backupDisk()
    {
        return Storage::disk(config('backup.backup.destination.disks')[0] ?? 'local');
    }

    private function ensureCentralBackupWorkspace(): ?JsonResponse
    {
        if (function_exists('tenant') && tenant('id')) {
            return response()->json([
                'message' => 'System backups are only available from the central admin workspace.',
            ], 403);
        }

        return null;
    }

    private function resolveBackupPath(string $id): ?string
    {
        $path = base64_decode($id, true);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $normalizedPath = trim(str_replace('\\', '/', $path), '/');

        return SystemBackupCatalog::isAllowedPath($normalizedPath) ? $normalizedPath : null;
    }
}
