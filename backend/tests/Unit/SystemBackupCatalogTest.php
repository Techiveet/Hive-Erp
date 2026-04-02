<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Modules\Core\Support\SystemBackupCatalog;
use PHPUnit\Framework\TestCase;

class SystemBackupCatalogTest extends TestCase
{
    public function test_it_builds_consistent_backup_filenames(): void
    {
        $filename = SystemBackupCatalog::buildFilename('all', 'automatic', Carbon::parse('2026-04-02 11:30:45'));

        $this->assertSame('hive-central-automatic-all-2026-04-02-113045.zip', $filename);
    }

    public function test_it_parses_new_backup_filename_metadata(): void
    {
        $metadata = SystemBackupCatalog::parseFilename('hive-central-manual-files-2026-04-02-010203.zip');

        $this->assertSame([
            'trigger' => 'manual',
            'type' => 'files',
        ], $metadata);
    }

    public function test_it_falls_back_for_legacy_backup_filenames(): void
    {
        $metadata = SystemBackupCatalog::parseFilename('legacy-auto-database-backup.zip');

        $this->assertSame([
            'trigger' => 'auto',
            'type' => 'db',
        ], $metadata);
    }
}
