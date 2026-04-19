<?php

namespace Tests\Unit;

use Tests\TestCase;

class TenantMediaStorageConfigTest extends TestCase
{
    public function test_media_library_defaults_to_filesystem_disk_when_media_disk_is_not_set(): void
    {
        putenv('MEDIA_DISK');
        putenv('FILESYSTEM_DISK=s3');
        $_ENV['FILESYSTEM_DISK'] = 's3';
        unset($_ENV['MEDIA_DISK'], $_SERVER['MEDIA_DISK']);
        $_SERVER['FILESYSTEM_DISK'] = 's3';

        $config = include dirname(__DIR__, 2) . '/config/media-library.php';

        $this->assertSame('s3', $config['disk_name']);
    }

    public function test_tenancy_filesystem_configuration_includes_the_s3_disk(): void
    {
        $config = include dirname(__DIR__, 2) . '/config/tenancy.php';

        $this->assertContains('s3', $config['filesystem']['disks']);
    }
}
