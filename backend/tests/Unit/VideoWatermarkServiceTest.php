<?php

namespace Tests\Unit;

use Modules\Core\Support\BrandSettingsStore;
use Modules\Core\Support\VideoWatermarkService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class VideoWatermarkServiceTest extends TestCase
{
    public function test_it_keeps_the_original_video_name_for_downloads(): void
    {
        $service = new VideoWatermarkService(new BrandSettingsStore());
        $media = new Media();
        $media->file_name = 'lesson-one.final-cut.mov';

        $method = new ReflectionMethod($service, 'buildDownloadFilename');
        $method->setAccessible(true);

        $this->assertSame('lesson-one.final-cut.mp4', $method->invoke($service, $media));
    }

    public function test_it_builds_a_single_bottom_corner_watermark_filter(): void
    {
        $service = new VideoWatermarkService(new BrandSettingsStore());

        $method = new ReflectionMethod($service, 'buildFilterGraph');
        $method->setAccessible(true);

        $graph = $method->invoke($service, 'Acme Academy', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf');

        $this->assertSame(1, substr_count($graph, 'drawtext='));
        $this->assertStringContainsString("text='Acme Academy'", $graph);
        $this->assertStringContainsString('fontsize=14', $graph);
        $this->assertStringContainsString('x=w-tw-10:y=h-th-10', $graph);
    }

    public function test_it_builds_a_bottom_corner_overlay_filter_for_brand_exports(): void
    {
        $service = new VideoWatermarkService(new BrandSettingsStore());

        $method = new ReflectionMethod($service, 'buildOverlayFilterGraph');
        $method->setAccessible(true);

        $graph = $method->invoke($service);

        $this->assertSame(
            '[0:v:0][1:v:0]overlay=x=main_w-overlay_w-10:y=main_h-overlay_h-10:format=auto:eof_action=repeat[branded]',
            $graph
        );
    }
}
