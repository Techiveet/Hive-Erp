<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\TemporaryDownloadSignature;
use Modules\Core\Models\Folder;
use Modules\Core\Models\FileEntry;
use Modules\Core\Jobs\PrepareVideoDownloadAsset;
use Modules\Core\Jobs\TranscodeVideoForStreaming;
use Modules\Core\Support\TenantMediaStorage;
use Modules\Core\Support\VideoWatermarkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

// 🚀 Intervention Image v3 Imports
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class FileManagerController extends Controller
{
    public function __construct(
        private readonly VideoWatermarkService $videoWatermarkService,
<<<<<<< HEAD
        private readonly TemporaryDownloadSignature $temporaryDownloadSignature
=======
        private readonly TenantMediaStorage $mediaStorage
>>>>>>> fdba7116f66fd5d462137665fb0c6cad6d63dd2d
    ) {}

    // =========================================================================
    // 1. Core Fetching & Metrics
    // =========================================================================

    public function index(Request $request)
    {
        $folderId = $request->input('folder_id');
        $filter = $request->input('filter', 'all');
        $userId = auth()->id();
        $pageSize = max(1, min(100, (int) $request->input('page_size', 50)));
        $page = max(1, (int) $request->input('page', 1));

        $foldersQuery = Folder::where('user_id', $userId);
        $filesQuery = FileEntry::with('media')->where('user_id', $userId);

        if ($filter === 'trash') {
            $foldersQuery->onlyTrashed();
            $filesQuery->onlyTrashed();
        } else {
            if ($filter === 'favorites') {
                $foldersQuery->where('is_favorite', true);
                $filesQuery->where('is_favorite', true);
            } elseif ($filter === 'recent') {
                $filesQuery->orderBy('created_at', 'desc')->take(20);
                $foldersQuery->whereRaw('1 = 0'); // Don't show folders in recent
            } elseif ($request->has('playlist_id')) {
                $playlistId = $request->input('playlist_id');
                $filesQuery->whereHas('playlists', function($q) use ($playlistId) {
                    $q->where('playlists.id', $playlistId);
                });
                $foldersQuery->whereRaw('1 = 0'); // No folders in playlist view
            } else {
                if ($folderId === null) {
                    $foldersQuery->whereNull('parent_id');
                    $filesQuery->whereNull('folder_id');
                } else {
                    $foldersQuery->where('parent_id', $folderId);
                    $filesQuery->where('folder_id', $folderId);
                }
            }
        }

        $metrics = Cache::remember(
            "file_metrics_{$this->currentTenantContextId()}_{$userId}",
            now()->addMinutes(5),
            fn () => $this->buildMetricsForUser((int) $userId)
        );

        $folders = $foldersQuery->orderBy('name')->paginate($pageSize, ['*'], 'page', $page);
        $files = $filesQuery->orderBy('created_at', 'desc')->paginate($pageSize, ['*'], 'page', $page);
        $this->queueMissingVideoStreams($files->items());

        return response()->json([
            'data' => [
                'folders' => $folders,
                'files' => $files,
                'page_size' => $pageSize,
                'page' => $page,
            ],
            'meta' => [
                'folders' => [
                    'total' => $folders->total(),
                    'current_page' => $folders->currentPage(),
                    'last_page' => $folders->lastPage(),
                ],
                'files' => [
                    'total' => $files->total(),
                    'current_page' => $files->currentPage(),
                    'last_page' => $files->lastPage(),
                ],
            ],
            'metrics' => $metrics
        ]);
    }

    public function signedStreamUrl(Request $request, int $id)
    {
        $fileEntry = FileEntry::findOrFail($id);
        $user = auth()->user();
        $isAdmin = (bool) ($user && method_exists($user, 'hasAdministrativeRole') && $user->hasAdministrativeRole());

        if (! $isAdmin && $fileEntry->user_id !== $user?->id) {
            abort(403, 'Unauthorized');
        }

        $tenantId = $this->currentTenantContextId();
        $expiresAt = now()->addMinutes(5)->timestamp;
        $payload = [
            'id' => $id,
            'tenant' => $tenantId,
            'uid' => (int) $user->id,
            'exp' => $expiresAt,
        ];

        return response()->json([
            'url' => url('/api/v1/media/stream/'.$id.'?'.http_build_query([
                'tenant' => $tenantId,
                'uid' => (int) $user->id,
                'exp' => $expiresAt,
                'sig' => $this->temporaryDownloadSignature->sign($payload),
                'signature' => $tenantId === 'central' ? null : app(\App\Support\TenantRequestSignature::class)->sign($tenantId),
            ])),
            'expires_at' => $expiresAt,
        ]);
    }

    // =========================================================================
    // 2. Uploads & Creation
    // =========================================================================

    public function createFolder(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:folders,id'
        ]);

        $this->assertOwnedFolderId($request->input('parent_id'));

        $folder = Folder::create([
            'name' => $request->input('name'),
            'parent_id' => $request->input('parent_id'),
            'user_id' => auth()->id(),
        ]);
        $this->forgetMetricsCacheForCurrentUser();

        return response()->json(['message' => 'Folder created', 'folder' => $folder], 201);
    }

    public function uploadFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:102400',
            'folder_id' => 'nullable|exists:folders,id',
            'base_name' => 'nullable|string|max:255',
            'original_name' => 'nullable|string|max:255',
            'custom_thumbnail' => 'nullable|image|max:5120',
            'upload_id' => 'required|string',
            'chunk_index' => 'required|integer|min:0',
            'total_chunks' => 'required|integer|min:1',
        ]);

        $this->assertOwnedFolderId($request->input('folder_id'));

        $chunkIndex = $request->input('chunk_index');
        $totalChunks = $request->input('total_chunks');
        $uploadId = preg_replace('/[^A-Za-z0-9_\-]/', '_', $request->input('upload_id'));
        $file = $request->file('file');

        $tempPath = storage_path('app/temp_uploads/' . $uploadId);

        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0777, true);
        }

        file_put_contents($tempPath, file_get_contents($file->getPathname()), FILE_APPEND);

        if ($chunkIndex < $totalChunks - 1) {
            return response()->json([
                'message' => 'Chunk received',
                'progress' => round(($chunkIndex / $totalChunks) * 100)
            ]);
        }

        try {
            $fileEntry = FileEntry::create([
                'folder_id' => $request->input('folder_id'),
                'user_id' => auth()->id(),
            ]);

            $originalName = basename((string) ($request->input('original_name') ?: $file->getClientOriginalName()));
            $mediaUploader = $fileEntry->addMedia($tempPath)
                                       ->usingName($request->input('base_name') ?: pathinfo($originalName, PATHINFO_FILENAME))
                                       ->usingFileName($originalName);

            $media = $mediaUploader->toMediaCollection('file', $this->mediaStorage->mediaDisk());

            if ($request->hasFile('custom_thumbnail')) {
                $fileEntry->addMedia($request->file('custom_thumbnail'))
                    ->toMediaCollection('custom_thumbnail', $this->mediaStorage->mediaDisk());
            }

            if (str_starts_with($media->mime_type, 'video/')) {
                $this->dispatchVideoStreamPreparation($fileEntry);
                $this->dispatchVideoDownloadPreparation($fileEntry);
            }
            $this->forgetMetricsCacheForCurrentUser();

            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            return response()->json(['message' => 'Upload complete', 'file' => $fileEntry->load('media')], 201);

        } catch (\Exception $e) {
            if (file_exists($tempPath)) unlink($tempPath);
            return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    public function saveEditedImage(Request $request)
    {
        $request->validate([
            'file' => 'required|image|max:20480',
            'original_id' => 'required|exists:file_entries,id'
        ]);

        $originalEntry = FileEntry::findOrFail($request->original_id);

        if ($originalEntry->user_id !== auth()->id()) abort(403, 'Unauthorized');

        try {
            $newFileEntry = FileEntry::create([
                'folder_id' => $originalEntry->folder_id,
                'user_id' => auth()->id(),
            ]);

            $originalMedia = $originalEntry->getFirstMedia('file');
            $baseName = $originalMedia ? pathinfo($originalMedia->file_name, PATHINFO_FILENAME) : 'edited_image';

            $uploadedFile = $request->file('file');
            $extension = $uploadedFile->getClientOriginalExtension() ?: 'png';

            $newName = $baseName . '_edited_' . time() . '.' . $extension;

            $newFileEntry->addMedia($uploadedFile)
                         ->usingName($newName)
                         ->usingFileName($newName)
                         ->toMediaCollection('file', $this->mediaStorage->mediaDisk());

            return response()->json([
                'message' => 'Edited image saved successfully',
                'file' => $newFileEntry->load('media')
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to save edited image: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 3. Organization & Operations (Rename, Move, Share)
    // =========================================================================

    public function renameItem(Request $request)
    {
        $request->validate([
            'type' => 'required|in:file,folder',
            'id' => 'required|integer',
            'name' => 'required|string|max:255'
        ]);

        $model = $request->type === 'folder' ? Folder::findOrFail($request->input('id')) : FileEntry::findOrFail($request->input('id'));

        if ($model->user_id !== auth()->id()) abort(403);

        $inputName = $request->input('name');
        if ($request->type === 'folder') {
            $model->update(['name' => $inputName]);
        } else {
            $media = $model->getFirstMedia('file');
            if ($media) {
                $extension = pathinfo($media->file_name, PATHINFO_EXTENSION);
                $media->name = $inputName;
                $media->file_name = $inputName . ($extension ? '.' . $extension : '');
                $media->save();

            }
        }
        return response()->json(['message' => 'Renamed successfully']);
    }

    public function moveItems(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'destination_folder_id' => 'nullable|integer|exists:folders,id'
        ]);

        $destId = $request->input('destination_folder_id');
        $this->assertOwnedFolderId($destId);

        foreach ($request->items as $item) {
            if ($item['type'] === 'folder') {
                if ($item['id'] !== $destId) {
                    Folder::where('id', $item['id'])->where('user_id', auth()->id())->update(['parent_id' => $destId]);
                }
            } else {
                FileEntry::where('id', $item['id'])->where('user_id', auth()->id())->update(['folder_id' => $destId]);
            }
        }
        return response()->json(['message' => 'Items moved successfully']);
    }

    public function toggleFavorite(Request $request, $type, $id)
    {
        $model = $type === 'folder' ? Folder::findOrFail($id) : FileEntry::findOrFail($id);
        if ($model->user_id !== auth()->id()) abort(403);

        $model->update(['is_favorite' => !$model->is_favorite]);
        return response()->json(['message' => 'Favorite toggled', 'is_favorite' => $model->is_favorite]);
    }

    public function generateShareLink(Request $request, $type, $id)
    {
        $token = bin2hex(random_bytes(16));
        return response()->json(['link' => url("/shared/{$token}")]);
    }

    // =========================================================================
    // 4. Trash & Recycle Bin Management
    // =========================================================================

    public function destroy($type, $id)
    {
        $model = $type === 'folder' ? Folder::findOrFail($id) : FileEntry::findOrFail($id);
        if ($model->user_id !== auth()->id()) abort(403);
        $model->delete();
        $this->forgetMetricsCacheForCurrentUser();
        return response()->json(['message' => ucfirst($type) . ' moved to recycle bin']);
    }

    public function restoreItems(Request $request)
    {
        $request->validate(['items' => 'required|array']);
        foreach ($request->items as $item) {
            $model = $item['type'] === 'folder' ? Folder::onlyTrashed()->find($item['id']) : FileEntry::onlyTrashed()->find($item['id']);
            if ($model && $model->user_id === auth()->id()) $model->restore();
        }
        $this->forgetMetricsCacheForCurrentUser();
        return response()->json(['message' => 'Items restored']);
    }

    public function forceDeleteItems(Request $request)
    {
        $request->validate(['items' => 'required|array']);
        foreach ($request->items as $item) {
            $model = $item['type'] === 'folder' ? Folder::onlyTrashed()->find($item['id']) : FileEntry::onlyTrashed()->find($item['id']);
            if ($model && $model->user_id === auth()->id()) $model->forceDelete();
        }
        $this->forgetMetricsCacheForCurrentUser();
        return response()->json(['message' => 'Items permanently deleted']);
    }

    public function emptyTrash()
    {
        $userId = auth()->id();
        Folder::onlyTrashed()->where('user_id', $userId)->forceDelete();
        FileEntry::onlyTrashed()->where('user_id', $userId)->forceDelete();
        $this->forgetMetricsCacheForCurrentUser();
        return response()->json(['message' => 'Recycle bin emptied']);
    }

    // =========================================================================
    // 5. Subtitles & Media Delivery
    // =========================================================================

    public function uploadSubtitle(Request $request, $id)
    {
        $request->validate([
            'subtitle' => 'required|file|max:2048',
            'language' => 'required|string|max:10',
            'label' => 'required|string|max:50'
        ]);

        $fileEntry = FileEntry::findOrFail($id);
        if ($fileEntry->user_id !== auth()->id()) abort(403);

        $isFirst = $fileEntry->getMedia('subtitles')->count() === 0;

        $fileEntry->addMedia($request->file('subtitle'))
                  ->withCustomProperties([
                      'language' => $request->language,
                      'label' => $request->label,
                      'default' => $isFirst
                  ])
                  ->toMediaCollection('subtitles', $this->mediaStorage->mediaDisk());

        return response()->json(['message' => 'Subtitle track added!', 'file' => $fileEntry->load('media')]);
    }

    public function serveSubtitle($uuid)
    {
        $media = Media::query()
            ->where('uuid', $uuid)
            ->where('model_type', FileEntry::class)
            ->where('collection_name', 'subtitles')
            ->firstOrFail();

        $fileEntry = FileEntry::query()
            ->whereKey($media->model_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $disk = $this->mediaStorage->mediaDisk($media);
        $relativePath = $this->mediaStorage->mediaRelativePath($media);

        if (!Storage::disk($disk)->exists($relativePath)) {
            abort(404, 'Subtitle not found.');
        }

        return $this->mediaStorage->streamResponse($disk, $relativePath, [
            'Content-Type' => 'text/vtt',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function deleteSubtitle($uuid)
    {
        $media = Media::where('uuid', $uuid)->firstOrFail();

        $fileEntry = FileEntry::find($media->model_id);
        if ($fileEntry && $fileEntry->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $media->delete();
        return response()->json(['message' => 'Subtitle deleted successfully', 'deleted_uuid' => $uuid], 200);
    }

    /**
     * Serve a tenant media file directly from the tenant-aware storage disk.
     *
     * Since tenancy is initialized via the X-Tenant header by InitializeTenantContext,
     * Storage::disk('public') is automatically suffixed to the correct tenant path.
     * This bypasses the broken getUrl() which always points to central storage.
     *
     * Query params:
     *   ?thumb=custom  → serve the custom_thumbnail collection
     *   ?thumb=1       → serve the generated video thumbnail conversion
     *   (none)         → serve the main file
     */
    public function serveMedia(Request $request, $id)
    {
        $fileEntry = FileEntry::where('user_id', auth()->id())->findOrFail($id);

        $thumb = $request->query('thumb');

        if ($thumb === 'custom') {
            $media = $fileEntry->getFirstMedia('custom_thumbnail');
            if (!$media) {
                abort(404, 'Thumbnail not found.');
            }

            $disk = $this->mediaStorage->mediaDisk($media);
            $relativePath = $this->mediaStorage->mediaRelativePath($media);

            if (!Storage::disk($disk)->exists($relativePath)) {
                abort(404, 'Thumbnail not found.');
            }

            return $this->mediaStorage->streamResponse($disk, $relativePath, [
                'Content-Type' => $media->mime_type ?: 'image/png',
                'Cache-Control' => 'private, max-age=86400',
                'Access-Control-Allow-Origin' => '*',
            ]);
        } elseif ($thumb === '1') {
            $media = $fileEntry->getFirstMedia('file');
            if (!$media) {
                abort(404, 'Thumbnail not found.');
            }

            $disk = $this->mediaStorage->mediaDisk($media);
            $relativePath = $media->hasGeneratedConversion('thumbnail')
                ? $this->mediaStorage->mediaRelativePath($media, ['thumbnail'])
                : $this->mediaStorage->mediaRelativePath($media);

            if (!Storage::disk($disk)->exists($relativePath)) {
                $relativePath = $this->mediaStorage->mediaRelativePath($media);
            }

            if (!Storage::disk($disk)->exists($relativePath)) {
                abort(404, 'Thumbnail not found.');
            }

            return $this->mediaStorage->streamResponse($disk, $relativePath, [
                'Content-Type'  => $media->hasGeneratedConversion('thumbnail') ? 'image/jpeg' : ($media->mime_type ?: 'application/octet-stream'),
                'Cache-Control' => 'private, max-age=86400',
                'Access-Control-Allow-Origin' => '*',
            ]);
        } else {
            $media = $fileEntry->getFirstMedia('file');
        }

        if (!$media) abort(404, 'File not found.');

        $disk = $this->mediaStorage->mediaDisk($media);
        $relativePath = $this->mediaStorage->mediaRelativePath($media);

        if (!Storage::disk($disk)->exists($relativePath)) {
            abort(404, 'File not found on disk.');
        }

        $mimeType = $media->mime_type ?: 'application/octet-stream';

        return $this->mediaStorage->streamResponse($disk, $relativePath, [
            'Content-Type'  => $mimeType,
            'Cache-Control' => 'private, max-age=86400',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }


    public function serveStream($mediaId, $filename)
    {
        $safeFilename = basename((string) $filename);

        if ($safeFilename !== $filename || !preg_match('/\A[\w.\-]+\z/', $safeFilename)) {
            abort(404, 'Stream chunk not found.');
        }

        $fileEntry = FileEntry::query()
            ->where('user_id', auth()->id())
            ->where('hls_path', 'like', $mediaId . '/processed/%')
            ->first();

        if (!$fileEntry) {
            abort(403, 'Unauthorized');
        }

        $media = $fileEntry->getFirstMedia('file');
        if (!$media) {
            abort(404, 'Stream source not found.');
        }

        $disk = $this->mediaStorage->mediaDisk($media);
        $relativePath = "{$mediaId}/processed/{$safeFilename}";

        if (!Storage::disk($disk)->exists($relativePath)) {
            abort(404, 'Stream chunk not found.');
        }

        $mime = str_ends_with($safeFilename, '.m3u8') ? 'application/vnd.apple.mpegurl' : 'video/MP2T';

        return $this->mediaStorage->streamResponse($disk, $relativePath, [
            'Content-Type' => $mime,
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function downloadMedia($id)
    {
        $fileEntry = FileEntry::findOrFail($id);

        // Allow file owner, admins and tenant admins to download
        $user = auth()->user();
        $isAdmin = (bool) ($user && method_exists($user, 'hasAdministrativeRole') && $user->hasAdministrativeRole());
        if (!$isAdmin && $fileEntry->user_id !== $user?->id) {
            abort(403, 'Unauthorized');
        }

        $media = $fileEntry->getFirstMedia('file');
        if (!$media) abort(404);

        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Expose-Headers' => 'Content-Disposition, X-Hive-Video-Download',
            'Cache-Control' => 'private, no-store, max-age=0',
        ];

        $isVideo = str_starts_with((string) $media->mime_type, 'video/');
        $isAudio = str_starts_with((string) $media->mime_type, 'audio/');

        if ($isVideo || $isAudio) {
            $shouldBrandDownload = $isVideo && $this->videoWatermarkService->shouldBrandDownloads();
            
            // For audio, we always "resolve" it through the service to check if the job ran
            // even if no branding is applied.
            $asset = ($shouldBrandDownload || $isAudio)
                ? $this->videoWatermarkService->resolveDownloadAsset($fileEntry)
                : null;

            if (
                $asset !== null
                && Storage::disk($asset['disk'])->exists($asset['relative_path'])
            ) {
                return Storage::disk($asset['disk'])->download(
                    $asset['relative_path'],
                    $asset['filename'],
                    $headers + [
                        'X-Hive-Video-Download' => $isVideo ? ($shouldBrandDownload ? 'branded' : 'original') : 'audio',
                    ]
                );
            }

            if ($shouldBrandDownload || $isAudio) {
                // If it's not ready yet, we dispatch and return 409
                $this->dispatchVideoDownloadPreparation($fileEntry, true);

                return response()->json([
                    'message' => ($isVideo ? 'Branded video' : 'Audio file') . ' is being prepared.',
                ], 409, $headers + [
                    'X-Hive-Video-Download' => 'preparing',
                    'Retry-After' => '3',
                ]);
            }
        }

        $disk = $this->mediaStorage->mediaDisk($media);
        $relativePath = $this->mediaStorage->mediaRelativePath($media);

        if (!Storage::disk($disk)->exists($relativePath)) {
            abort(404, 'File not found on disk.');
        }

        return Storage::disk($disk)->download($relativePath, $media->file_name, $headers + [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
            'X-Hive-Video-Download' => 'original',
        ]);
    }


    public function getFileDetails($id)
    {
        $fileEntry = FileEntry::with('media')
            ->where('user_id', auth()->id())
            ->findOrFail($id);
        $media = $fileEntry->getFirstMedia('file');
        if (!$media) abort(404);
        $disk = $this->mediaStorage->mediaDisk($media);

        if (
            str_starts_with((string) $media->mime_type, 'video/')
            && $this->videoWatermarkService->shouldBrandDownloads()
            && $this->videoWatermarkService->resolveDownloadAsset($fileEntry) === null
        ) {
            $this->dispatchVideoDownloadPreparation($fileEntry);
        }

        if (
            str_starts_with((string) $media->mime_type, 'video/')
            && (!$fileEntry->hls_path || !Storage::disk($disk)->exists($fileEntry->hls_path))
        ) {
            $this->dispatchVideoStreamPreparation($fileEntry);
        }

        $qualities = [
            'original' => url("/api/v1/files/{$fileEntry->id}/serve"),
        ];

        return response()->json([
            'file' => $fileEntry,
            'video_versions' => array_filter($qualities)
        ]);
    }

    /**
     * Prepares a file for download by checking if background processing is needed.
     * Returns "ready" if the file can be downloaded instantly, or "processing"
     * if the watermark is still being burned.
     */
    public function prepareDownload($id)
    {
        $fileEntry = FileEntry::with('media')->findOrFail($id);
        $user = auth()->user();
        $isAdmin = (bool) ($user && method_exists($user, 'hasAdministrativeRole') && $user->hasAdministrativeRole());

        if (!$isAdmin && $fileEntry->user_id !== $user?->id) {
            abort(403, 'Unauthorized');
        }

        $media = $fileEntry->getFirstMedia('file');

        if (!$media) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        $isVideo = str_starts_with((string) $media->mime_type, 'video/');
        $isAudio = str_starts_with((string) $media->mime_type, 'audio/');
        $shouldBrandDownload = $isVideo && $this->videoWatermarkService->shouldBrandDownloads();

        if ($shouldBrandDownload || $isAudio) {
            $asset = $this->videoWatermarkService->resolveDownloadAsset($fileEntry);

            if ($asset && Storage::disk($asset['disk'])->exists($asset['relative_path'])) {
                return response()->json([
                    'status' => 'ready',
                    'message' => ($isVideo ? 'Video' : 'Audio') . ' is ready for download.',
                    'progress' => 100
                ]);
            }

            // If the asset is not ready yet, enqueue preparation on the media worker.
            $this->dispatchVideoDownloadPreparation($fileEntry, true);

            // Fetch progress from cache (updated by VideoWatermarkService)
            $progress = Cache::get("download_progress_{$id}", 0);

            return response()->json([
                'status' => 'processing',
                'message' => $isVideo ? 'Watermark is being applied...' : 'Preparing audio for download...',
                'progress' => (int) $progress,
                'retry_after' => 2,
            ]);
        }

        // For non-video files or if branding is disabled
        return response()->json([
            'status' => 'ready',
            'message' => 'File is ready for download.'
        ]);
    }

    private function dispatchVideoStreamPreparation(FileEntry $fileEntry): void
    {
        $tenantId = $this->currentTenantContextId();
        $dispatchKey = TranscodeVideoForStreaming::dispatchMarkerKey((int) $fileEntry->getKey(), $tenantId);

        if (!Cache::add($dispatchKey, 1, $this->mediaDispatchMarkerExpiration())) {
            return;
        }

        try {
            TranscodeVideoForStreaming::dispatch((int) $fileEntry->getKey(), $tenantId);
        } catch (\Throwable $exception) {
            Cache::forget($dispatchKey);

            throw $exception;
        }
    }

    private function dispatchVideoDownloadPreparation(FileEntry $fileEntry, bool $primeProgress = false): void
    {
        $tenantId = $this->currentTenantContextId();
        $dispatchKey = PrepareVideoDownloadAsset::dispatchMarkerKey((int) $fileEntry->getKey(), $tenantId);
        $progressKey = "download_progress_{$fileEntry->getKey()}";

        if ($primeProgress) {
            $existingProgress = (int) Cache::get($progressKey, 0);
            Cache::put($progressKey, max(1, $existingProgress), $this->mediaDispatchMarkerExpiration());
        }

        if (!Cache::add($dispatchKey, 1, $this->mediaDispatchMarkerExpiration())) {
            return;
        }

        try {
            PrepareVideoDownloadAsset::dispatch((int) $fileEntry->getKey(), $tenantId);
        } catch (\Throwable $exception) {
            Cache::forget($dispatchKey);

            if ($primeProgress) {
                Cache::forget($progressKey);
            }

            throw $exception;
        }
    }

    private function currentTenantContextId(): string
    {
        return (function_exists('tenant') && tenant('id'))
            ? (string) tenant('id')
            : 'central';
    }

    private function mediaDispatchMarkerExpiration(): \DateTimeInterface
    {
        $ttlSeconds = max(600, (int) config('media-library.ffmpeg_timeout', 900) + 600);

        return now()->addSeconds($ttlSeconds);
    }

    private function assertOwnedFolderId($folderId): void
    {
        if ($folderId === null || $folderId === '') {
            return;
        }

        $ownsFolder = Folder::query()
            ->whereKey($folderId)
            ->where('user_id', auth()->id())
            ->exists();

        abort_unless($ownsFolder, 403, 'Unauthorized folder access.');
    }

    private function queueMissingVideoStreams(iterable $files): void
    {
        foreach ($files as $fileEntry) {
            if (!$fileEntry instanceof FileEntry) {
                continue;
            }

            $media = $fileEntry->getFirstMedia('file');
            if (!$media || !str_starts_with((string) $media->mime_type, 'video/')) {
                continue;
            }

            $disk = $this->mediaStorage->mediaDisk($media);
            $needsStreamPreparation = !$fileEntry->hls_path || !Storage::disk($disk)->exists($fileEntry->hls_path);

            if ($needsStreamPreparation) {
                $this->dispatchVideoStreamPreparation($fileEntry);
            }
        }
    }

    private function buildMetricsForUser(int $userId): array
    {
        $mediaItems = DB::table('media')
            ->join('file_entries', 'media.model_id', '=', 'file_entries.id')
            ->where('media.model_type', FileEntry::class)
            ->where('file_entries.user_id', $userId)
            ->whereNull('file_entries.deleted_at')
            ->select('media.mime_type', 'media.size')
            ->get();

        $metrics = [
            'total_used' => 0,
            'images' => ['size' => 0, 'count' => 0],
            'videos' => ['size' => 0, 'count' => 0],
            'docs' => ['size' => 0, 'count' => 0],
            'audio_other' => ['size' => 0, 'count' => 0],
        ];

        foreach ($mediaItems as $item) {
            $metrics['total_used'] += (int) $item->size;
            if (str_starts_with((string) $item->mime_type, 'image/')) {
                $metrics['images']['size'] += (int) $item->size;
                $metrics['images']['count']++;
            } elseif (str_starts_with((string) $item->mime_type, 'video/')) {
                $metrics['videos']['size'] += (int) $item->size;
                $metrics['videos']['count']++;
            } elseif (preg_match('/(pdf|document|text|msword|excel|spreadsheet|powerpoint|presentation|csv)/i', (string) $item->mime_type)) {
                $metrics['docs']['size'] += (int) $item->size;
                $metrics['docs']['count']++;
            } else {
                $metrics['audio_other']['size'] += (int) $item->size;
                $metrics['audio_other']['count']++;
            }
        }

        return $metrics;
    }

    private function forgetMetricsCacheForCurrentUser(): void
    {
        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        Cache::forget("file_metrics_{$this->currentTenantContextId()}_{$userId}");
    }

    // =========================================================================
    // 🚀 6. PHOTO AI BACKGROUND REMOVAL (Via internal rembg container)
    // =========================================================================

    public function removeBackground(Request $request)
    {
        $request->validate([
            'file' => 'required|image|max:15360',
        ]);

        $file = $request->file('file');

        try {
            $response = Http::timeout(60)->attach(
                'file',
                file_get_contents($file->getPathname()),
                $file->getClientOriginalName()
            )->post('http://rembg:5000/api/remove');

            if (!$response->successful()) {
                Log::error('Local AI Failed: ' . $response->body());
                return response()->json(['message' => 'AI Processor failed to isolate the subject.'], 422);
            }

            return response($response->body(), 200, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Access-Control-Allow-Origin' => '*',
            ]);

        } catch (\Exception $e) {
            Log::error('AI Exception: ' . $e->getMessage());
            return response()->json(['message' => 'Server Error: Could not connect to the local AI microservice.'], 500);
        }
    }

    // =========================================================================
    // 🚀 7. PHP MAGIC WAND LOGO REMOVAL (With High-Quality Anti-Aliasing)
    // =========================================================================

    public function removeLogoBackground(Request $request)
    {
        $request->validate([
            'file' => 'required|image|max:20480',
            'tolerance' => 'nullable|numeric'
        ]);

        try {
            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            $image = $manager->read($request->file('file')->getPathname());

            if ($image->width() > 3000) {
                $image->scaleDown(width: 3000);
            }

            $gdImage = $image->core()->native();
            $width = imagesx($gdImage);
            $height = imagesy($gdImage);

            $output = imagecreatetruecolor($width, $height);
            imagealphablending($output, false);
            imagesavealpha($output, true);

            $transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
            imagefill($output, 0, 0, $transparent);

            $bgPixel = imagecolorat($gdImage, 0, 0);
            $bgColors = imagecolorsforindex($gdImage, $bgPixel);

            $tolerance = $request->input('tolerance', 45);
            $softness = 25;

            for ($x = 0; $x < $width; $x++) {
                for ($y = 0; $y < $height; $y++) {
                    $pixel = imagecolorat($gdImage, $x, $y);
                    $colors = imagecolorsforindex($gdImage, $pixel);

                    $distance = sqrt(
                        pow($colors['red'] - $bgColors['red'], 2) +
                        pow($colors['green'] - $bgColors['green'], 2) +
                        pow($colors['blue'] - $bgColors['blue'], 2)
                    );

                    if ($distance <= $tolerance) {
                        imagesetpixel($output, $x, $y, $transparent);
                    }
                    elseif ($distance <= $tolerance + $softness) {
                        $ratio = ($distance - $tolerance) / $softness;
                        $originalAlpha = isset($colors['alpha']) ? $colors['alpha'] : 0;
                        $blendedAlpha = (int)(127 - ((127 - $originalAlpha) * $ratio));

                        $color = imagecolorallocatealpha($output, $colors['red'], $colors['green'], $colors['blue'], $blendedAlpha);
                        imagesetpixel($output, $x, $y, $color);
                    } else {
                        $alpha = isset($colors['alpha']) ? $colors['alpha'] : 0;
                        $color = imagecolorallocatealpha($output, $colors['red'], $colors['green'], $colors['blue'], $alpha);
                        imagesetpixel($output, $x, $y, $color);
                    }
                }
            }

            ob_start();
            // 🚀 CRITICAL FIX: Save as max compression PNG (9) to prevent massive file sizes!
            imagepng($output, null, 9);
            $pngData = ob_get_clean();
            imagedestroy($output);

            return response($pngData, 200, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Access-Control-Allow-Origin' => '*',
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Magic Wand Exception: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to process logo background.'], 500);
        }
    }
}
