<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class FileEntry extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;

    protected $fillable = ['folder_id', 'user_id', 'is_favorite', 'hls_path', 'watermarked_path'];

    protected $appends = ['media_details'];

    public function folder() { return $this->belongsTo(Folder::class); }

    public function playlists()
    {
        return $this->belongsToMany(Playlist::class, 'playlist_file_entry');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->singleFile();
        $this->addMediaCollection('custom_thumbnail')->singleFile();

        // Register the subtitles collection (can hold multiple .vtt files)
        $this->addMediaCollection('subtitles');
    }

    public function resolveDisplayTitle(?Media $media = null): string
    {
        $media ??= $this->getFirstMedia('file');

        $candidates = [
            $media?->name,
            pathinfo((string) $media?->file_name, PATHINFO_FILENAME),
            'Untitled File',
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $candidate = trim($candidate);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        return 'Untitled File';
    }

    public function getMediaDetailsAttribute()
    {
        $media = $this->getFirstMedia('file');
        if (!$media) return null;

        $title = $this->resolveDisplayTitle($media);

        // Determine if we're in a tenant context. If so, getUrl() returns the wrong URL
        // because the public symlink only covers central storage. We serve tenant assets
        // through the authenticated API endpoint which uses the tenant-aware disk instead.
        $isTenant = function_exists('tenant') && tenant('id') !== null;

        $mediaUrl = $isTenant
            ? url("/api/v1/files/{$this->id}/serve")
            : $media->getUrl();

        $thumbnailUrl = null;
        if ($this->getFirstMedia('custom_thumbnail')) {
            $thumbnailMedia = $this->getFirstMedia('custom_thumbnail');
            $thumbnailUrl = $isTenant
                ? url("/api/v1/files/{$this->id}/serve?thumb=custom")
                : $thumbnailMedia->getUrl();
        } elseif ($media->hasGeneratedConversion('thumbnail')) {
            // Video thumbnail — also served through the API in tenant context
            $thumbnailUrl = $isTenant
                ? url("/api/v1/files/{$this->id}/serve?thumb=1")
                : $media->getUrl('thumbnail');
        } elseif (str_starts_with($media->mime_type, 'image/')) {
            $thumbnailUrl = $mediaUrl;
        }

        $subtitles = $this->getMedia('subtitles')->map(function ($sub) {
            return [
                'uuid' => $sub->uuid,
                'src' => $sub->getUrl(),
                'srcLang' => $sub->getCustomProperty('language', 'en'),
                'label' => $sub->getCustomProperty('label', 'Subtitle'),
                'default' => (bool) $sub->getCustomProperty('default', false),
            ];
        })->toArray();

        return [
            'uuid' => $media->uuid,
            'name' => $media->file_name,
            'title' => $title,
            'download_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'human_size' => $media->human_readable_size,
            'url' => $mediaUrl,
            'thumbnail' => $thumbnailUrl,
            'hls_path' => $this->hls_path,
            'subtitles' => $subtitles,
            // Fallback array while HLS is processing
            'video_versions' => [
                ['label' => 'Original', 'url' => $mediaUrl]
            ],
        ];
    }


    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        // Only generate ONE real image thumbnail for the video cover
        if ($media && str_starts_with($media->mime_type, 'video/')) {
            $this->addMediaConversion('thumbnail')
                ->width(800)
                ->height(450)
                ->extractVideoFrameAtSecond(1)
                ->performOnCollections('file');
        }
    }
}
