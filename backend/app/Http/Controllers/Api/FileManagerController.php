<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use App\Models\FileEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FileManagerController extends Controller
{
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
                $foldersQuery->whereRaw('1 = 0');
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
            } elseif (str_contains($item->mime_type, 'pdf') || str_contains($item->mime_type, 'document') || str_contains($item->mime_type, 'text/')) {
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
            'base_name' => 'nullable|string|max:255'
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

    public function toggleFavorite(Request $request, $type, $id)
    {
        $model = $type === 'folder' ? Folder::findOrFail($id) : FileEntry::findOrFail($id);
        if ($model->user_id !== auth()->id()) abort(403);
        $model->update(['is_favorite' => !$model->is_favorite]);
        return response()->json(['message' => 'Favorite toggled', 'is_favorite' => $model->is_favorite]);
    }

    public function destroy($type, $id)
    {
        $model = $type === 'folder' ? Folder::findOrFail($id) : FileEntry::findOrFail($id);
        if ($model->user_id !== auth()->id()) abort(403);
        $model->delete();
        return response()->json(['message' => ucfirst($type) . ' moved to recycle bin']);
    }

    public function serveSubtitle($uuid)
    {
        $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::where('uuid', $uuid)->firstOrFail();

        return response(file_get_contents($media->getPath()), 200, [
            'Content-Type' => 'text/vtt',
            'Access-Control-Allow-Origin' => '*'
        ]);
    }

    public function deleteSubtitle($uuid)
    {
        $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::where('uuid', $uuid)->firstOrFail();

        $fileEntry = FileEntry::find($media->model_id);
        if ($fileEntry && $fileEntry->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $media->delete();

        return response()->json([
            'message' => 'Subtitle deleted successfully',
            'deleted_uuid' => $uuid
        ], 200);
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

    public function serveStream($mediaId, $filename)
    {
        $path = storage_path("app/public/{$mediaId}/processed/{$filename}");

        if (!file_exists($path)) {
            abort(404, "Stream chunk not found.");
        }

        $mime = str_ends_with($filename, '.m3u8') ? 'application/vnd.apple.mpegurl' : 'video/MP2T';

        return response()->file($path, [
            'Content-Type' => $mime,
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    // 🚀 NEW: Secure Fetch to completely bypass Canvas CORS issues
    public function downloadMedia($id)
    {
        $fileEntry = FileEntry::findOrFail($id);

        if ($fileEntry->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        $media = $fileEntry->getFirstMedia('file');
        if (!$media) abort(404);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type',
        ]);
    }

    public function saveEditedImage(Request $request)
    {
        $request->validate([
            'file' => 'required|image|max:20480',
            'original_id' => 'required|exists:file_entries,id'
        ]);

        $originalEntry = FileEntry::findOrFail($request->original_id);

        if ($originalEntry->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        try {
            $newFileEntry = FileEntry::create([
                'folder_id' => $originalEntry->folder_id,
                'user_id' => auth()->id(),
            ]);

            $originalMedia = $originalEntry->getFirstMedia('file');
            $baseName = $originalMedia ? pathinfo($originalMedia->file_name, PATHINFO_FILENAME) : 'edited_image';
            $newName = $baseName . '_edited_' . time() . '.jpg';

            $newFileEntry->addMedia($request->file('file'))
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
}
