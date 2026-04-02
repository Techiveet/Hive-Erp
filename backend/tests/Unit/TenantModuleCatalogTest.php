<?php

namespace Tests\Unit;

use Modules\Tenancy\Support\TenantModuleCatalog;
use PHPUnit\Framework\TestCase;

class TenantModuleCatalogTest extends TestCase
{
    public function test_resolve_uses_plan_defaults_when_payload_is_missing(): void
    {
        $resolved = TenantModuleCatalog::resolve(null, 'startup');

        $this->assertSame(
            ['image_editor', 'video_player', 'document_converter'],
            $resolved['enabled_modules']
        );
        $this->assertSame(3, $resolved['module_count']);
    }

    public function test_normalize_for_storage_filters_unknown_modules_and_custom_entries(): void
    {
        $normalized = TenantModuleCatalog::normalizeForStorage([
            'enabled_modules' => ['image_editor', 'unknown_module', 'video_player'],
            'custom_modules' => [
                ['name' => 'Audio Studio', 'description' => 'Audio processing workspace'],
                ['name' => ''],
            ],
        ], 'business', 'admin@tenant.test');

        $this->assertSame(['image_editor', 'video_player'], $normalized['enabled_modules']);
        $this->assertSame('audio-studio', $normalized['custom_modules'][0]['slug']);
        $this->assertSame('admin@tenant.test', $normalized['updated_by']);
        $this->assertNotEmpty($normalized['updated_at']);
    }
}
