<?php

namespace Tests\Unit;

use Modules\Subscription\Support\TenantModuleCatalog;
use PHPUnit\Framework\TestCase;

class TenantModuleCatalogTest extends TestCase
{
    public function test_resolve_uses_plan_defaults_when_payload_is_missing(): void
    {
        $resolved = TenantModuleCatalog::resolve(null, 'startup');

        $this->assertSame(
            ['mailbox', 'file_manager', 'image_editor', 'audio_player', 'document_converter'],
            $resolved['enabled_modules']
        );
        $this->assertSame(5, $resolved['module_count']);
    }

    public function test_normalize_for_storage_filters_unknown_modules_and_custom_entries(): void
    {
        $normalized = TenantModuleCatalog::normalizeForStorage([
            'enabled_modules' => ['image_editor', 'unknown_module', 'audio_player', 'warehouse_management'],
            'custom_modules' => [
                ['name' => 'Audio Studio', 'description' => 'Audio processing workspace'],
                ['name' => ''],
            ],
        ], 'business', 'admin@tenant.test');

        $this->assertSame(['image_editor', 'audio_player', 'warehouse_management'], $normalized['enabled_modules']);
        $this->assertSame('audio-studio', $normalized['custom_modules'][0]['slug']);
        $this->assertSame('admin@tenant.test', $normalized['updated_by']);
        $this->assertNotEmpty($normalized['updated_at']);
    }

    public function test_catalog_contains_audio_player_and_warehouse_management(): void
    {
        $slugs = TenantModuleCatalog::slugs();

        $this->assertContains('audio_player', $slugs);
        $this->assertContains('warehouse_management', $slugs);
    }

    public function test_resolve_exposes_full_catalog_for_tenant_upgrade_choices(): void
    {
        $resolved = TenantModuleCatalog::resolve([
            'enabled_modules' => ['project_management'],
            'custom_modules' => [],
        ], 'business', [], 'software-development');

        $catalogSlugs = collect($resolved['catalog_modules'])->pluck('slug')->all();

        $this->assertContains('project_management', $catalogSlugs);
        $this->assertContains('warehouse_management', $catalogSlugs);
        $this->assertContains('inventory_control', $catalogSlugs);
        $this->assertContains('audio_player', $catalogSlugs);
    }

    public function test_price_overrides_are_normalized_for_modules_submodules_and_features(): void
    {
        $normalized = TenantModuleCatalog::normalizePriceOverrides([
            'modules' => [
                'audio_player' => ['monthly_price_etb' => '1250.50'],
                'unknown_module' => ['monthly_price_etb' => 99],
            ],
            'submodules' => [
                'audio_player:playlists' => ['monthly_price_etb' => '75'],
            ],
            'features' => [
                'audio_player.playlists.index' => ['monthly_price_etb' => '25'],
            ],
        ]);

        $this->assertSame(1250.5, $normalized['modules']['audio_player']['monthly_price_etb']);
        $this->assertArrayNotHasKey('unknown_module', $normalized['modules']);
        $this->assertSame(75.0, $normalized['submodules']['audio_player:playlists']['monthly_price_etb']);
        $this->assertSame(25.0, $normalized['features']['audio_player.playlists.index']['monthly_price_etb']);
    }

    public function test_catalog_price_overrides_drive_addon_quotes(): void
    {
        $quote = TenantModuleCatalog::quoteForUpgrade('startup', ['video_player'], [
            'modules' => [
                'video_player' => ['monthly_price_etb' => 777],
            ],
        ]);

        $this->assertSame(777.0, $quote['line_items'][0]['amount_etb']);
        $this->assertSame(777.0, $quote['total_etb']);
    }

    public function test_submodule_prices_roll_up_to_module_and_plan_prices(): void
    {
        $overrides = TenantModuleCatalog::normalizePriceOverrides([
            'modules' => [
                'project_management' => ['monthly_price_etb' => 999],
                'warehouse_management' => ['monthly_price_etb' => 999],
            ],
            'submodules' => [
                'project_management:projects' => ['monthly_price_etb' => 600],
                'project_management:reports' => ['monthly_price_etb' => 250],
                'warehouse_management:warehouses' => ['monthly_price_etb' => 300],
            ],
        ]);

        $catalog = TenantModuleCatalog::catalogMap($overrides);

        $this->assertSame(850.0, $catalog['project_management']['monthly_price_etb']);
        $this->assertSame(300.0, $catalog['warehouse_management']['monthly_price_etb']);
        $this->assertSame(1150.0, TenantModuleCatalog::planAmountForModules([
            'project_management',
            'warehouse_management',
        ], $overrides));
    }

    public function test_addon_modules_are_classified_for_separate_checkout(): void
    {
        $catalog = TenantModuleCatalog::catalogMap([
            'modules' => [
                'video_player' => ['monthly_price_etb' => 399, 'billing_type' => 'addon'],
                'project_management' => ['monthly_price_etb' => 899, 'billing_type' => 'module'],
            ],
        ]);

        $this->assertTrue($catalog['video_player']['is_addon']);
        $this->assertSame('addon', $catalog['video_player']['billing_type']);
        $this->assertFalse($catalog['project_management']['is_addon']);
        $this->assertSame('module', $catalog['project_management']['billing_type']);
    }
}
