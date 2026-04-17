<?php

namespace Tests\Unit;

use Modules\Core\Models\FileEntry;
use PHPUnit\Framework\TestCase;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class FileEntryDisplayTitleTest extends TestCase
{
    public function test_it_prefers_the_media_name_for_display_title(): void
    {
        $fileEntry = new FileEntry();
        $media = new Media();
        $media->name = 'Premium Lesson';
        $media->file_name = 'premium-lesson.mp4';

        $this->assertSame('Premium Lesson', $fileEntry->resolveDisplayTitle($media));
    }

    public function test_it_falls_back_to_the_filename_without_extension(): void
    {
        $fileEntry = new FileEntry();
        $media = new Media();
        $media->name = '';
        $media->file_name = 'advanced-report.final.mp4';

        $this->assertSame('advanced-report.final', $fileEntry->resolveDisplayTitle($media));
    }
}
