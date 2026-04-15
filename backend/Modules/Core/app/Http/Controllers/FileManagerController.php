<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Core\Models\Folder;
use Modules\Core\Models\FileEntry;
use Modules\Core\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

// 🚀 Intervention Image v3 Imports
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class FileManagerController extends Controller
{
    // =========================================================================
    // 1. Core Fetching & Metrics
    // =========================================================================

    public function index(Request $request)
    {
        $folderId = $request->input('folder_id');
        $filter = $request->input('filter', 'all');
        $userId = auth()->id();

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
            } else {
                $foldersQuery->where('parent_id', $folderId);
                $filesQuery->where('folder_id', $folderId);
            }
        }

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
            $metrics['total_used'] += $item->size;
            if (str_starts_with($item->mime_type, 'image/')) {
                $metrics['images']['size'] += $item->size;
                $metrics['images']['count']++;
            } elseif (str_starts_with($item->mime_type, 'video/')) {
                $metrics['videos']['size'] += $item->size;
                $metrics['videos']['count']++;
            } elseif (preg_match('/(pdf|document|text|msword|excel|spreadsheet|powerpoint|presentation|csv)/i', $item->mime_type)) {
                $metrics['docs']['size'] += $item->size;
                $metrics['docs']['count']++;
            } else {
                $metrics['audio_other']['size'] += $item->size;
                $metrics['audio_other']['count']++;
            }
        }

        return response()->json([
            'data' => [
                'folders' => $foldersQuery->orderBy('name')->get(),
                'files' => $filesQuery->orderBy('created_at', 'desc')->get(),
            ],
            'metrics' => $metrics
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

        $folder = Folder::create([
            'name' => $request->name,
            'parent_id' => $request->parent_id,
            'user_id' => auth()->id(),
        ]);

        return response()->json(['message' => 'Folder created', 'folder' => $folder], 201);
    }

    public function uploadFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:102400',
            'folder_id' => 'nullable|exists:folders,id',
            'base_name' => 'nullable|string|max:255',
            'upload_id' => 'required|string',
            'chunk_index' => 'required|integer|min:0',
            'total_chunks' => 'required|integer|min:1',
        ]);

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
                'folder_id' => $request->folder_id,
                'user_id' => auth()->id(),
            ]);

            $originalName = $request->input('original_name');
            $mediaUploader = $fileEntry->addMedia($tempPath)
                                       ->usingName($request->input('base_name') ?: pathinfo($originalName, PATHINFO_FILENAME))
                                       ->usingFileName($originalName);

            $media = $mediaUploader->toMediaCollection('file');

            if ($request->hasFile('custom_thumbnail')) {
                $fileEntry->addMedia($request->file('custom_thumbnail'))->toMediaCollection('custom_thumbnail');
            }

            if (str_starts_with($media->mime_type, 'video/') && class_exists(\App\Jobs\TranscodeVideoForStreaming::class)) {
                \App\Jobs\TranscodeVideoForStreaming::dispatch($fileEntry);
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
                         ->toMediaCollection('file');

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

        $model = $request->type === 'folder' ? Folder::findOrFail($request->id) : FileEntry::findOrFail($request->id);

        if ($model->user_id !== auth()->id()) abort(403);

        if ($request->type === 'folder') {
            $model->update(['name' => $request->name]);
        } else {
            $media = $model->getFirstMedia('file');
            if ($media) {
                $extension = pathinfo($media->file_name, PATHINFO_EXTENSION);
                $media->name = $request->name;
                $media->file_name = $request->name . ($extension ? '.' . $extension : '');
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

        $destId = $request->destination_folder_id;

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
        return response()->json(['message' => ucfirst($type) . ' moved to recycle bin']);
    }

    public function restoreItems(Request $request)
    {
        $request->validate(['items' => 'required|array']);
        foreach ($request->items as $item) {
            $model = $item['type'] === 'folder' ? Folder::onlyTrashed()->find($item['id']) : FileEntry::onlyTrashed()->find($item['id']);
            if ($model && $model->user_id === auth()->id()) $model->restore();
        }
        return response()->json(['message' => 'Items restored']);
    }

    public function forceDeleteItems(Request $request)
    {
        $request->validate(['items' => 'required|array']);
        foreach ($request->items as $item) {
            $model = $item['type'] === 'folder' ? Folder::onlyTrashed()->find($item['id']) : FileEntry::onlyTrashed()->find($item['id']);
            if ($model && $model->user_id === auth()->id()) $model->forceDelete();
        }
        return response()->json(['message' => 'Items permanently deleted']);
    }

    public function emptyTrash()
    {
        $userId = auth()->id();
        Folder::onlyTrashed()->where('user_id', $userId)->forceDelete();
        FileEntry::onlyTrashed()->where('user_id', $userId)->forceDelete();
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
                  ->toMediaCollection('subtitles');

        return response()->json(['message' => 'Subtitle track added!', 'file' => $fileEntry->load('media')]);
    }

    public function serveSubtitle($uuid)
    {
        $media = Media::where('uuid', $uuid)->firstOrFail();
        return response(file_get_contents($media->getPath()), 200, [
            'Content-Type' => 'text/vtt',
            'Access-Control-Allow-Origin' => '*'
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

    public function serveStream($mediaId, $filename)
    {
        $path = storage_path("app/public/{$mediaId}/processed/{$filename}");

        if (!file_exists($path)) abort(404, "Stream chunk not found.");

        $mime = str_ends_with($filename, '.m3u8') ? 'application/vnd.apple.mpegurl' : 'video/MP2T';

        return response()->file($path, [
            'Content-Type' => $mime,
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function downloadMedia($id)
    {
        $fileEntry = FileEntry::findOrFail($id);

        if ($fileEntry->user_id !== auth()->id()) abort(403, 'Unauthorized');

        $media = $fileEntry->getFirstMedia('file');
        if (!$media) abort(404);

        $filePath = $media->getPath();
        $mimeType = $media->mime_type;
        $fileName  = $media->file_name;

        // ── Only watermark video files ──────────────────────────────────────
        if (!str_starts_with($mimeType, 'video/')) {
            return response()->file($filePath, [
                'Content-Type'                => $mimeType,
                'Content-Disposition'         => 'attachment; filename="' . $fileName . '"',
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        // ── Fetch app title from settings (cached 1 hour) ──────────────────
        $tenantPrefix = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';
        $appTitle = Cache::remember("watermark_app_title_{$tenantPrefix}", 3600, function () {
            return trim((string) Setting::where('key', 'app_title')->value('value')) ?: 'HIVE.OS';
        });

        // Escape special characters that FFmpeg drawtext cannot handle
        $safeTitle = str_replace(["'", ':', '\\'], ["\\'", '\\:', '\\\\'], $appTitle);

        // ── Check FFmpeg is available ───────────────────────────────────────
        $ffmpeg = trim((string) shell_exec('which ffmpeg 2>/dev/null'));
        if (empty($ffmpeg)) {
            Log::warning('FFmpeg not found — serving video without watermark.');
            return response()->file($filePath, [
                'Content-Type'                => $mimeType,
                'Content-Disposition'         => 'attachment; filename="' . $fileName . '"',
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        // ── Build temp output path ──────────────────────────────────────────
        $tempDir  = storage_path('app/temp_watermark');
        if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);
        $tempOut  = $tempDir . '/' . uniqid('wm_', true) . '.mp4';

        // ── Resolve font path – use vendor DejaVu font (guaranteed by Composer) ──
        // dompdf ships DejaVuSans-Bold.ttf, which is always present in the container.
        $fontPath = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        // Fallback chain for system fonts (Alpine, Ubuntu, etc.)
        if (!file_exists($fontPath)) $fontPath = '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf';
        if (!file_exists($fontPath)) $fontPath = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        if (!file_exists($fontPath)) {
            Log::warning('[Watermark] No font found – serving without watermark.');
            return response()->file($filePath, [
                'Content-Type'                => $mimeType,
                'Content-Disposition'         => 'attachment; filename="' . $fileName . '"',
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        $fontArg = "fontfile={$fontPath}:";

        $filter = "drawtext={$fontArg}" .
                  "text='{$safeTitle}':" .
                  "fontsize=18:" .
                  "fontcolor=white@0.35:" .
                  "x=w-tw-16:y=h-th-16:" .   // bottom-right, 16px padding (Udemy-style)
                  "shadowx=1:shadowy=1:shadowcolor=black@0.40";

        // -c:a copy  → no audio re-encode (fast)
        // -preset ultrafast -crf 28 → prioritize speed so the request doesn't timeout
        $cmd = sprintf(
            '%s -i %s -vf %s -c:a copy -preset ultrafast -crf 28 -y %s 2>&1',
            escapeshellarg($ffmpeg),
            escapeshellarg($filePath),
            escapeshellarg($filter),
            escapeshellarg($tempOut)
        );

        Log::info("[Watermark] Running FFmpeg for file {$id}");
        $output = shell_exec($cmd);

        if (!file_exists($tempOut) || filesize($tempOut) === 0) {
            Log::error("[Watermark] FFmpeg failed for file {$id}: {$output}");
            // Graceful fallback — serve without watermark
            return response()->file($filePath, [
                'Content-Type'                => $mimeType,
                'Content-Disposition'         => 'attachment; filename="' . $fileName . '"',
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        // ── Stream the watermarked file then clean up ───────────────────────
        return response()->streamDownload(function () use ($tempOut) {
            $handle = fopen($tempOut, 'rb');
            while (!feof($handle)) {
                echo fread($handle, 8192);
                flush();
            }
            fclose($handle);
            @unlink($tempOut); // delete temp file after streaming
        }, $fileName, [
            'Content-Type'                => 'video/mp4',
            'Content-Disposition'         => 'attachment; filename="' . $fileName . '"',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    public function getFileDetails($id)
    {
        $fileEntry = FileEntry::with('media')->findOrFail($id);
        $media = $fileEntry->getFirstMedia('file');

        $qualities = [
            'original' => $media->getUrl(),
            'q_720p'   => $media->hasGeneratedConversion('720p') ? $media->getUrl('720p') : null,
            'q_1080p'  => $media->hasGeneratedConversion('1080p') ? $media->getUrl('1080p') : null,
            'q_4k'     => $media->hasGeneratedConversion('4k') ? $media->getUrl('4k') : null,
        ];

        return response()->json([
            'file' => $fileEntry,
            'video_versions' => array_filter($qualities)
        ]);
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
