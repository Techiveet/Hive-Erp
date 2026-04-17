<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\AuthContext;
use App\Support\TenantRequestSignature;
use Illuminate\Http\Request;
use Modules\Core\Models\FileEntry;
use Modules\Tenancy\Models\Tenant;
use Stancl\Tenancy\Tenancy;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * MediaStreamController
 *
 * Serves media files (audio, video) using a token passed via query string.
 * This is needed because browser native <audio> and <video> elements cannot
 * send custom Authorization headers — they must use URLs.
 *
 * IMPORTANT: Tenant must be initialized BEFORE looking up the token because
 * personal_access_tokens live in the tenant database, not central.
 *
 * Route: GET /api/v1/media/stream/{id}?token=xxx[&tenant=xxx]
 */
class MediaStreamController extends Controller
{
    public function __construct(
        private readonly Tenancy $tenancy,
        private readonly AuthContext $authContext,
        private readonly TenantRequestSignature $tenantRequestSignature
    ) {}

    public function stream(Request $request, $id)
    {
        $rawToken = $request->query('token');
        if (!$rawToken) {
            abort(401, 'Missing media token.');
        }

        // 1. Initialize tenant context FIRST (tokens live in the tenant DB)
        $tenantId = (string) ($request->query('tenant') ?: 'central');
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

        // 2. Look up the token AFTER tenant context is initialized
        $accessToken = PersonalAccessToken::findToken($rawToken);
        if (!$accessToken || !$accessToken->tokenable) {
            abort(401, 'Invalid or expired media token.');
        }

        $requiredAbility = $this->authContext->ability($tenantId);
        $tokenAbilities = $accessToken->abilities ?? [];
        if (
            !in_array('*', $tokenAbilities, true)
            && !in_array($requiredAbility, $tokenAbilities, true)
        ) {
            abort(403, 'Invalid media token context.');
        }

        $user = $accessToken->tokenable;

        // 3. Find the file entry — admins can stream any file, users only their own
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
