<?php

namespace Tests\Unit;

use Modules\Core\Jobs\PrepareVideoDownloadAsset;
use Modules\Core\Jobs\TranscodeVideoForStreaming;
use Tests\TestCase;

class MediaQueueJobConfigTest extends TestCase
{
    public function test_prepare_video_download_asset_uses_priority_download_queue(): void
    {
        config([
            'media-library.queue_connection_name' => 'redis-media',
            'media-library.download_queue_name' => 'media-downloads',
        ]);

        $job = new PrepareVideoDownloadAsset(42, 'central');

        $this->assertSame('redis-media', $job->connection);
        $this->assertSame('media-downloads', $job->queue);
        $this->assertSame(
            'media-dispatch:download:central:42',
            PrepareVideoDownloadAsset::dispatchMarkerKey(42, 'central')
        );
    }

    public function test_transcode_video_for_streaming_uses_processing_queue(): void
    {
        config([
            'media-library.queue_connection_name' => 'redis-media',
            'media-library.queue_name' => 'media-processing',
        ]);

        $job = new TranscodeVideoForStreaming(42, 'tenant-alpha');

        $this->assertSame('redis-media', $job->connection);
        $this->assertSame('media-processing', $job->queue);
        $this->assertSame(
            'media-dispatch:stream:tenant-alpha:42',
            TranscodeVideoForStreaming::dispatchMarkerKey(42, 'tenant-alpha')
        );
    }
}
