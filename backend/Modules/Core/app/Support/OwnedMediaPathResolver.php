<?php

namespace Modules\Core\Support;

use Modules\Core\Models\FileEntry;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class OwnedMediaPathResolver
{
    public function extractMediaId(?string $path): ?int
    {
        $value = trim((string) $path);

        if ($value === '') {
            return null;
        }

        $normalized = str_replace('\\', '/', $value);

        if (preg_match('~(?:^|/)(\d+)(?:/|$)~', $normalized, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    public function resolveOwnedMediaFromPath(?string $path, int $ownerId): ?Media
    {
        $mediaId = $this->extractMediaId($path);

        if (!$mediaId) {
            return null;
        }

        $media = Media::query()->find($mediaId);

        if (!$media || $media->model_type !== FileEntry::class) {
            return null;
        }

        $isOwned = FileEntry::query()
            ->whereKey($media->model_id)
            ->where('user_id', $ownerId)
            ->exists();

        return $isOwned ? $media : null;
    }

    public function isOwnedMediaPath(?string $path, int $ownerId): bool
    {
        $value = trim((string) $path);

        if ($value === '') {
            return true;
        }

        return $this->resolveOwnedMediaFromPath($value, $ownerId) !== null;
    }
}
