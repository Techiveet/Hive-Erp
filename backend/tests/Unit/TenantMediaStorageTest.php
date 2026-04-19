<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Storage;
use Modules\Core\Support\TenantMediaStorage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class TenantMediaStorageTest extends TestCase
{
    public function test_media_disk_falls_back_to_media_library_disk_configuration(): void
    {
        config([
            'media-library.disk_name' => 's3',
            'filesystems.default' => 'local',
        ]);

        $service = new TenantMediaStorage();
        $media = $this->mockMedia(null, '42/original.mp4');

        $this->assertSame('s3', $service->mediaDisk($media));
    }

    public function test_media_relative_path_uses_available_path_with_requested_conversion_candidates(): void
    {
        $service = new TenantMediaStorage();
        $media = $this->mockMedia('s3', '42/conversions/thumb.jpg', function (Media $mock): void {
            $mock->expects($this->once())
                ->method('getAvailablePathRelativeToRoot')
                ->with(['thumbnail'])
                ->willReturn('42/conversions/thumb.jpg');
        });

        $this->assertSame(
            '42/conversions/thumb.jpg',
            $service->mediaRelativePath($media, ['thumbnail'])
        );
    }

    public function test_it_can_stage_a_remote_media_object_to_local_temp_storage(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('42/original.mp4', 'remote-video-data');

        $service = new TenantMediaStorage();
        $media = $this->mockMedia('s3', '42/original.mp4');

        $localPath = $service->stageMediaToLocalTemp($media);

        $this->assertFileExists($localPath);
        $this->assertSame('remote-video-data', file_get_contents($localPath));

        @unlink($localPath);
    }

    private function mockMedia(?string $disk, string $relativePath, ?callable $configure = null): Media
    {
        $media = $this->getMockBuilder(Media::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAvailablePathRelativeToRoot'])
            ->getMock();

        $media->disk = $disk;
        $media->method('getAvailablePathRelativeToRoot')->willReturn($relativePath);

        if ($configure !== null) {
            $configure($media);
        }

        return $media;
    }
}
