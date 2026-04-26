<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\TemporaryDownloadSignature;
use App\Support\TenantRequestSignature;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Core\Models\FileEntry;
use Modules\Core\Support\TenantMediaStorage;
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
        private readonly TemporaryDownloadSignature $temporaryDownloadSignature,
        private readonly TenantMediaStorage $mediaStorage
    ) {}

    public function stream(Request $request, $id)
    {
        \Illuminate\Support\Facades\Log::info('MediaStream: Request received', [
            'id' => $id,
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'headers' => $request->headers->all()
        ]);
        // 1. Resolve and Verify Tenant Context first
        $tenantId = (string) ($request->query('tenant') ?: 'central');
        $tenantSignature = $request->query('signature');

        if (
            $tenantId !== 'central'
            && !$this->tenantRequestSignature->matches($tenantId, is_string($tenantSignature) ? $tenantSignature : null)
        ) {
            \Illuminate\Support\Facades\Log::debug('MediaStream: Tenant context signature mismatch', ['tenant' => $tenantId, 'sig' => $tenantSignature]);
            abort(403, 'Invalid tenant context signature.');
        }

        // Initialize tenant context if provided
        if ($tenantId && $tenantId !== 'central') {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                \Illuminate\Support\Facades\Log::debug('MediaStream: Tenant not found', ['tenantId' => $tenantId]);
                abort(404, 'Tenant not found.');
            }
            if (!$this->tenancy->initialized || (string) $this->tenancy->tenant->getTenantKey() !== (string) $tenant->getTenantKey()) {
                $this->tenancy->initialize($tenant);
            }
        } elseif ($this->tenancy->initialized) {
            $this->tenancy->end();
        }

        // 2. Authentication (Token or Signature)
        $userId = (int) $request->query('uid', 0);
        $expiresAt = (int) $request->query('exp', 0);
        $signature = (string) $request->query('sig', '');
        $token = (string) $request->query('token', '');

        $user = null;
        $isAuthenticatedViaToken = false;

        \Illuminate\Support\Facades\Log::debug('MediaStream: Starting auth check', [
            'id' => $id,
            'token_provided' => !empty($token),
            'tenant_initialized' => $this->tenancy->initialized,
            'current_tenant' => $this->tenancy->initialized ? $this->tenancy->tenant->getTenantKey() : 'central'
        ]);

        if ($token !== '') {
            $tokenWasCentral = false;

            // First try current context (could be tenant or central)
            $accessToken = PersonalAccessToken::findToken($token);

            // 🚀 FALLBACK: If token not found in current (tenant) DB, try central DB.
            if (!$accessToken && function_exists('tenancy') && $this->tenancy->initialized) {
                \Illuminate\Support\Facades\Log::debug('MediaStream: Falling back to central DB for token lookup');
                $accessToken = tenancy()->central(function () use ($token) {
                    $t = PersonalAccessToken::findToken($token);
                    if ($t) {
                        // Crucial: Load user and roles while in central context
                        $t->load(['tokenable.roles', 'tokenable.permissions']);
                    }
                    return $t;
                });
                if ($accessToken) {
                    $tokenWasCentral = true;
                    \Illuminate\Support\Facades\Log::debug('MediaStream: Token found in central DB');
                }
            } else if ($accessToken) {
                \Illuminate\Support\Facades\Log::debug('MediaStream: Token found in current DB');
            }

            if ($accessToken && $accessToken->tokenable) {
                $user = $accessToken->tokenable;

                // 🚀 Check permissions in the same context as the token
                $hasPermission = $tokenWasCentral
                    ? tenancy()->central(fn() => $user->hasAdministrativeRole() || $user->hasPermissionAcrossGuards(['media.view', 'media.*']))
                    : ($user->hasAdministrativeRole() || $user->hasPermissionAcrossGuards(['media.view', 'media.*']));

                if ($hasPermission) {
                    $isAuthenticatedViaToken = true;
                    $userId = $user->id; // Ensure userId is sync'd for file resolution
                    \Illuminate\Support\Facades\Log::debug('MediaStream: Authorized via Token', [
                        'user_id' => $user->id,
                        'is_central' => $tokenWasCentral
                    ]);
                } else {
                    \Illuminate\Support\Facades\Log::warning('MediaStream: Token valid but user lacks permissions', [
                        'user_id' => $user->id,
                        'is_central' => $tokenWasCentral
                    ]);
                }
            }
        }


        if (!$isAuthenticatedViaToken) {
            if ($userId <= 0 || $expiresAt <= 0 || $signature === '') {
                \Illuminate\Support\Facades\Log::debug('MediaStream: Missing signature auth parameters', ['uid' => $userId, 'exp' => $expiresAt, 'sig' => $signature]);
                abort(401, 'Missing stream authorization parameters.');
            }

            if ($expiresAt < now()->timestamp) {
                \Illuminate\Support\Facades\Log::debug('MediaStream: URL expired', ['exp' => $expiresAt, 'now' => now()->timestamp]);
                abort(401, 'Stream URL expired.');
            }

            $signedPayload = [
                'id' => (int) $id,
                'tenant' => $tenantId,
                'uid' => $userId,
                'exp' => $expiresAt,
            ];

            if (! $this->temporaryDownloadSignature->matches($signedPayload, $signature)) {
                \Illuminate\Support\Facades\Log::debug('MediaStream: Signature mismatch', ['payload' => $signedPayload, 'sig' => $signature]);
                abort(403, 'Invalid stream signature.');
            }
        }

        // 3. Final User and File Resolution
        if (!$user) {
            $user = \Modules\Identity\Models\User::query()->find($userId);
        }

        if (! $user) {
            \Illuminate\Support\Facades\Log::debug('MediaStream: Final user lookup failed', ['userId' => $userId, 'isAuthenticatedViaToken' => $isAuthenticatedViaToken]);
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

        $disk = $this->mediaStorage->mediaDisk($media);
        $path = $this->resolveStreamPath($media, $disk);
        if (!file_exists($path)) {
            abort(404, 'File not found on disk.');
        }

        $mimeType = $media->mime_type ?: 'application/octet-stream';
        $fileSize = filesize($path);
        $inlineFileName = $this->mediaStorage->sanitizeInlineFilename((string) ($media->file_name ?: 'media-stream'));
        $cleanupAfterStream = $this->shouldCleanupStreamPath($disk);

        // 5. Support range requests for video/audio seeking
        $headers = [
            'Content-Type' => $mimeType,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Disposition' => "inline; filename=\"{$inlineFileName}\"",
            'Access-Control-Expose-Headers' => 'Content-Length, Content-Range, Accept-Ranges, Content-Disposition',
            'Access-Control-Allow-Origin' => '*',
            'Cross-Origin-Resource-Policy' => 'cross-origin',
            'X-Content-Type-Options' => 'nosniff',
        ];

        // Handle range request (browser seeking)
        $rangeHeader = $request->header('Range');
        if ($rangeHeader) {
            preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $matches);
            $start = (int) $matches[1];
            $end = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : $fileSize - 1;
            $end = min($end, $fileSize - 1);
            $length = $end - $start + 1;

            $headers['Content-Range'] = "bytes {$start}-{$end}/{$fileSize}";
            $headers['Content-Length'] = $length;

            $fp = fopen($path, 'rb');
            fseek($fp, $start);

            return response()->stream(function () use ($fp, $length, $cleanupAfterStream, $path) {
                $remaining = $length;
                while ($remaining > 0 && !feof($fp)) {
                    $chunk = min(8192, $remaining);
                    echo fread($fp, $chunk);
                    $remaining -= $chunk;
                    flush();
                }
                fclose($fp);

                if ($cleanupAfterStream && is_file($path)) {
                    @unlink($path);
                }
            }, 206, $headers);
        }

        // No range — stream the full file
        $headers['Content-Length'] = $fileSize;

        $fp = fopen($path, 'rb');
        return response()->stream(function () use ($fp, $cleanupAfterStream, $path) {
            while (!feof($fp)) {
                echo fread($fp, 8192);
                flush();
            }
            fclose($fp);

            if ($cleanupAfterStream && is_file($path)) {
                @unlink($path);
            }
        }, 200, $headers);
    }

    private function resolveStreamPath($media, string $disk): string
    {
        if ($this->shouldCleanupStreamPath($disk)) {
            return $this->mediaStorage->stageMediaToLocalTemp($media);
        }

        return $media->getPath();
    }

    private function shouldCleanupStreamPath(string $disk): bool
    {
        return (string) config("filesystems.disks.{$disk}.driver") !== 'local';
    }
}
