<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\TemporaryDownloadSignature;
use App\Support\TenantRequestSignature;
use Illuminate\Http\Request;
use Modules\Core\Models\FileEntry;
use Modules\Tenancy\Models\Tenant;
use Stancl\Tenancy\Tenancy;

/**
 * MediaStreamController
 *
 * Serves media files (audio, video) using a short-lived signed URL.
 *
 * Route: GET /api/v1/media/stream/{id}?tenant=...&uid=...&exp=...&sig=...
 */
class MediaStreamController extends Controller
{
    public function __construct(
        private readonly Tenancy $tenancy,
        private readonly TenantRequestSignature $tenantRequestSignature,
        private readonly TemporaryDownloadSignature $temporaryDownloadSignature
    ) {}

    public function stream(Request $request, $id)
    {
        $tenantId = (string) ($request->query('tenant') ?: 'central');
        $userId = (int) $request->query('uid', 0);
        $expiresAt = (int) $request->query('exp', 0);
        $signature = (string) $request->query('sig', '');

        if ($userId <= 0 || $expiresAt <= 0 || $signature === '') {
            abort(401, 'Missing stream authorization parameters.');
        }

        if ($expiresAt < now()->timestamp) {
            abort(401, 'Stream URL expired.');
        }

        $signedPayload = [
            'id' => (int) $id,
            'tenant' => $tenantId,
            'uid' => $userId,
            'exp' => $expiresAt,
        ];

        if (! $this->temporaryDownloadSignature->matches($signedPayload, $signature)) {
            abort(403, 'Invalid stream signature.');
        }

        // Initialize tenant context before querying tenant-scoped data.
        $tenantSignature = $request->query('signature');

        if (
            $tenantId !== 'central'
            && !$this->tenantRequestSignature->matches($tenantId, is_string($tenantSignature) ? $tenantSignature : null)
        ) {
            abort(403, 'Invalid tenant context signature.');
        }

        if ($tenantId && $tenantId !== 'central') {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                abort(404, 'Tenant not found.');
            }
            $this->tenancy->initialize($tenant);
        } elseif ($this->tenancy->initialized) {
            $this->tenancy->end();
        }

        $user = \Modules\Identity\Models\User::query()->find($userId);
        if (! $user) {
            abort(401, 'Stream user not found.');
        }

        // Find the file entry — admins can stream any file, users only their own.
        $isAdmin = method_exists($user, 'hasAdministrativeRole') && $user->hasAdministrativeRole();
        $fileQuery = FileEntry::query();
        if (!$isAdmin) {
            $fileQuery->where('user_id', $user->id);
        }
        $fileEntry = $fileQuery->find($id);
        if (!$fileEntry) {
            abort(404, 'File not found.');
        }

        $media = $fileEntry->getFirstMedia('file');
        if (!$media) {
            abort(404, 'Media not found.');
        }

        // 4. Resolve the physical path (tenant-aware after initialize above)
        $path = $media->getPath();
        if (!file_exists($path)) {
            abort(404, 'File not found on disk.');
        }

        $mimeType = $media->mime_type ?: 'application/octet-stream';
        $fileSize = filesize($path);
        $inlineFileName = $this->inlineFilename((string) ($media->file_name ?: 'media-stream'));

        // 5. Support range requests for video/audio seeking
        $headers = [
            'Content-Type'                => $mimeType,
            'Accept-Ranges'               => 'bytes',
            'Cache-Control'               => 'private, no-store, max-age=0',
            'Content-Disposition'         => "inline; filename=\"{$inlineFileName}\"",
            'Access-Control-Expose-Headers' => 'Content-Length, Content-Range, Accept-Ranges, Content-Disposition',
            'Access-Control-Allow-Origin' => '*',
            'Cross-Origin-Resource-Policy' => 'cross-origin',
            'X-Content-Type-Options'      => 'nosniff',
        ];

        // Handle range request (browser seeking)
        $rangeHeader = $request->header('Range');
        if ($rangeHeader) {
            preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $matches);
            $start = (int) $matches[1];
            $end   = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : $fileSize - 1;
            $end   = min($end, $fileSize - 1);
            $length = $end - $start + 1;

            $headers['Content-Range']  = "bytes {$start}-{$end}/{$fileSize}";
            $headers['Content-Length'] = $length;

            $fp = fopen($path, 'rb');
            fseek($fp, $start);

            return response()->stream(function () use ($fp, $length) {
                $remaining = $length;
                while ($remaining > 0 && !feof($fp)) {
                    $chunk = min(8192, $remaining);
                    echo fread($fp, $chunk);
                    $remaining -= $chunk;
                    flush();
                }
                fclose($fp);
            }, 206, $headers);
        }

        // No range — stream the full file
        $headers['Content-Length'] = $fileSize;

        $fp = fopen($path, 'rb');
        return response()->stream(function () use ($fp) {
            while (!feof($fp)) {
                echo fread($fp, 8192);
                flush();
            }
            fclose($fp);
        }, 200, $headers);
    }

    private function inlineFilename(string $filename): string
    {
        $safeFilename = basename($filename);
        $safeFilename = preg_replace('/[^A-Za-z0-9._ -]+/', '', $safeFilename) ?: 'media-stream.mp4';

        return trim($safeFilename) !== '' ? trim($safeFilename) : 'media-stream.mp4';
    }
}
