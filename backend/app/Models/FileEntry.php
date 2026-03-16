<?php

namespace App\Models;

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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->singleFile();
        $this->addMediaCollection('custom_thumbnail')->singleFile();

        // Register the subtitles collection (can hold multiple .vtt files)
        $this->addMediaCollection('subtitles');
    }

public function getMediaDetailsAttribute()
    {
        $media = $this->getFirstMedia('file');
        if (!$media) return null;

        $thumbnailUrl = null;
        if ($this->getFirstMedia('custom_thumbnail')) {
            $thumbnailUrl = $this->getFirstMedia('custom_thumbnail')->getUrl();
        } elseif ($media->hasGeneratedConversion('thumbnail')) {
            $thumbnailUrl = $media->getUrl('thumbnail');
        } elseif (str_starts_with($media->mime_type, 'image/')) {
            $thumbnailUrl = $media->getUrl();
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
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'human_size' => $media->human_readable_size,
            'url' => $media->getUrl(),
            'thumbnail' => $thumbnailUrl,
            'hls_path' => $this->hls_path,
            'subtitles' => $subtitles,
            // Fallback array while HLS is processing
            'video_versions' => [
                ['label' => 'Original', 'url' => $media->getUrl()]
            ],
        ];
    }

    public function registerMediaConversions(\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
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
