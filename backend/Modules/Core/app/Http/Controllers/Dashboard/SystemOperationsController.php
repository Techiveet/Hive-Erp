<?php

namespace Modules\Core\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Jobs\RunSystemBackup;

class SystemOperationsController extends Controller
{
    private function userHasPermission($user, string $permission): bool
    {
        if (!$user) {
            return false;
        }

        $user->loadMissing(['permissions', 'roles.permissions']);

        return $user->permissions->contains('name', $permission)
            || $user->roles->flatMap->permissions->contains('name', $permission)
            || $user->roles->contains('name', 'Super Admin');
    }

    public function flushCache(Request $request)
    {
        Artisan::call('optimize:clear');
        $currentTenant = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';

        activity('System Operations')
            ->causedBy(auth()->user())
            ->tap(function($activity) use ($currentTenant) { $activity->tenant_id = $currentTenant; })
            ->log('Flushed all system caches and optimized memory.');

        return response()->json(['message' => 'System cache successfully purged.']);
    }

    public function triggerBackup(Request $request)
    {
        $request->validate(['type' => 'required|in:db,files,all']);
        $currentTenant = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';

        RunSystemBackup::dispatch(auth()->user(), $currentTenant, $request->type);

        activity('System Operations')
            ->causedBy(auth()->user())
            ->tap(function($activity) use ($currentTenant) { $activity->tenant_id = $currentTenant; })
            ->log("Manual {$request->type} backup job dispatched to Horizon workers.");

        return response()->json(['message' => 'Backup initiated. Monitor the audit log for completion.']);
    }

    public function updateSchedule(Request $request)
    {
        $request->validate([
            'frequency' => 'required|in:daily,weekly,monthly',
            'time' => 'required|string',
            'day' => 'required|integer|min:1|max:31'
        ]);

        set_system_setting('backup_frequency', $request->frequency);
        set_system_setting('backup_time', $request->time);
        set_system_setting('backup_day', $request->day);

        activity('System Operations')
            ->causedBy(auth()->user())
            ->log("Automated backup schedule updated to {$request->frequency} at {$request->time}.");

        return response()->json(['message' => 'Automated backup schedule updated successfully.']);
    }

    public function getBackups(Request $request)
    {
        $isTenant = function_exists('tenant') && tenant('id');
        $currentTenant = $isTenant ? tenant('id') : env('APP_NAME', 'Laravel');

        $disk = Storage::disk(config('backup.backup.destination.disks')[0] ?? 'local');

        $directories = [
            'HiveErp',
            'private/HiveErp',
            $currentTenant,
            config('backup.backup.name', 'Laravel'),
            env('APP_NAME', 'Laravel'),
            'Hive',
            'backups',
            ''
        ];

        $files = [];

        foreach (array_unique($directories) as $dir) {
            $contents = $dir === '' ? $disk->files() : ($disk->exists($dir) ? $disk->allFiles($dir) : []);

            foreach ($contents as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
                    $files[] = [
                        'id' => base64_encode($file),
                        'name' => basename($file),
                        'type' => str_contains(basename($file), 'db') ? 'db' : (str_contains(basename($file), 'files') ? 'files' : 'all'),
                        'trigger' => 'manual',
                        'size' => round($disk->size($file) / 1048576, 2) . ' MB',
                        'created_at' => \Carbon\Carbon::createFromTimestamp($disk->lastModified($file))->toIso8601String(),
                    ];
                }
            }
        }

        $files = collect($files)->unique('id')->values()->toArray();
        usort($files, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

        return response()->json(['data' => $files]);
    }

    // 🚀 Download large files safely using Token Auth
    public function downloadBackup(Request $request, $id)
    {
        $tokenStr = $request->query('token');
        $tokenStr = str_replace('Bearer ', '', $tokenStr); // Safety cleanup
        $token = \Laravel\Sanctum\PersonalAccessToken::findToken($tokenStr);

        if (!$token || !$token->tokenable) {
            return response()->json(['error' => 'Unauthorized or expired token.'], 401);
        }

        $user = $token->tokenable;

        if (!$this->userHasPermission($user, 'view_backups')) {
            return response()->json(['error' => 'Forbidden. Missing backup access permission.'], 403);
        }

        $path = base64_decode($id);
        $disk = Storage::disk(config('backup.backup.destination.disks')[0] ?? 'local');

        if (!$disk->exists($path)) {
            return response()->json(['error' => 'Backup file not found on disk.'], 404);
        }

        activity('System Operations')
            ->causedBy($user)
            ->log("Downloaded system backup archive: " . basename($path));

        return response()->streamDownload(function () use ($disk, $path) {
            $stream = $disk->readStream($path);
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, basename($path), [
            'Content-Type' => $disk->mimeType($path),
            'Content-Length' => $disk->size($path),
        ]);
    }

    public function deleteBackup(Request $request, $id)
    {
        $path = base64_decode($id);
        $disk = Storage::disk(config('backup.backup.destination.disks')[0] ?? 'local');

        if ($disk->exists($path)) {
            $disk->delete($path);
        }

        activity('System Operations')
            ->causedBy(auth()->user())
            ->log("Deleted system backup archive: " . basename($path));

        return response()->json(['message' => 'Backup deleted successfully.']);
    }
}
