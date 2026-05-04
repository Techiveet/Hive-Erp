<?php

namespace Tests\Unit;

use Modules\Subscription\Support\SubscriptionFeatureMap;
use PHPUnit\Framework\TestCase;

class SubscriptionFeatureMapTest extends TestCase
{
    public function test_inventory_paths_map_to_inventory_subscription_module(): void
    {
        $feature = (new SubscriptionFeatureMap())->featureForRequestPath('api/v1/inventory/products');

        $this->assertSame('inventory_control', $feature['module_slug']);
        $this->assertSame('inventory', $feature['submodule_slug']);
    }

    public function test_workflow_paths_map_to_workflow_subscription_module(): void
    {
        $feature = (new SubscriptionFeatureMap())->featureForRequestPath('api/v1/workflow-approvals');

        $this->assertSame('workflow_automation', $feature['module_slug']);
        $this->assertSame('approvals', $feature['submodule_slug']);
    }

    public function test_identity_management_paths_map_to_security_management(): void
    {
        $feature = (new SubscriptionFeatureMap())->featureForRequestPath('api/v1/tenant/users');

        $this->assertSame('security_management', $feature['module_slug']);
        $this->assertSame('users', $feature['submodule_slug']);
    }

    public function test_playlist_paths_map_to_file_manager_subscription_module(): void
    {
        $feature = (new SubscriptionFeatureMap())->featureForRequestPath('api/v1/playlists');

        $this->assertSame('file_manager', $feature['module_slug']);
        $this->assertSame(['file_manager', 'media_library', 'video_player', 'audio_player'], $feature['module_slugs']);
        $this->assertSame('playlists', $feature['submodule_slug']);
    }

    public function test_file_paths_accept_storage_or_video_subscription_modules(): void
    {
        $feature = (new SubscriptionFeatureMap())->featureForRequestPath('api/v1/files/123/signed-stream-url');

        $this->assertSame('file_manager', $feature['module_slug']);
        $this->assertSame(['file_manager', 'media_library', 'video_player', 'audio_player'], $feature['module_slugs']);
        $this->assertSame('files', $feature['submodule_slug']);
    }

    public function test_warehouse_paths_map_to_warehouse_subscription_module(): void
    {
        $feature = (new SubscriptionFeatureMap())->featureForRequestPath('api/v1/warehouse/warehouses');

        $this->assertSame('warehouse_management', $feature['module_slug']);
        $this->assertSame('warehouses', $feature['submodule_slug']);
    }

    public function test_audio_file_paths_accept_audio_player_subscription_module(): void
    {
        $feature = (new SubscriptionFeatureMap())->featureForRequestPath('api/v1/files/123/signed-stream-url');

        $this->assertContains('audio_player', $feature['module_slugs']);
    }

    public function test_converter_routes_are_grouped_under_converter_families(): void
    {
        $map = new SubscriptionFeatureMap();

        $this->assertSame('html-to-pdf', $map->featureForRequestPath('api/v1/convert/html-to-pdf')['submodule_slug']);
        $this->assertSame('pdf-documents', $map->featureForRequestPath('api/v1/convert/document')['submodule_slug']);
        $this->assertSame('video-audio', $map->featureForRequestPath('api/v1/convert/media')['submodule_slug']);
    }

    public function test_project_management_routes_are_split_into_real_submodules(): void
    {
        $map = new SubscriptionFeatureMap();

        $this->assertSame('overview', $map->featureForRequestPath('api/v1/project-management/summary')['submodule_slug']);
        $this->assertSame('projects', $map->featureForRequestPath('api/v1/project-management/projects')['submodule_slug']);
        $this->assertSame('team', $map->featureForRequestPath('api/v1/project-management/projects/{project}/members')['submodule_slug']);
        $this->assertSame('boards', $map->featureForRequestPath('api/v1/project-management/boards')['submodule_slug']);
        $this->assertSame('tasks', $map->featureForRequestPath('api/v1/project-management/tasks')['submodule_slug']);
        $this->assertSame('checklists', $map->featureForRequestPath('api/v1/project-management/tasks/{task}/checklists')['submodule_slug']);
        $this->assertSame('comments', $map->featureForRequestPath('api/v1/project-management/tasks/{task}/comments')['submodule_slug']);
    }

    public function test_feature_matrix_marks_subscribed_and_unsubscribed_modules_with_nested_features(): void
    {
        $matrix = (new SubscriptionFeatureMap())->matrixForCatalogModules([
            [
                'slug' => 'audio_player',
                'name' => 'Audio Player',
                'description' => 'Listen to tenant media.',
                'category' => 'Creative Suite',
                'tone' => 'cyan',
                'included_in_plan' => true,
                'status' => 'active',
                'monthly_price_etb' => 199,
            ],
            [
                'slug' => 'warehouse_management',
                'name' => 'Warehouse Management',
                'description' => 'Manage warehouses.',
                'category' => 'Business Apps',
                'tone' => 'teal',
                'included_in_plan' => false,
                'status' => 'inactive',
                'monthly_price_etb' => 699,
            ],
        ]);

        $modules = collect($matrix['modules'])->keyBy('slug');

        $this->assertTrue($modules['audio_player']['subscribed']);
        $this->assertSame('active', $modules['audio_player']['status']);
        $this->assertContains('audio-playback', collect($modules['audio_player']['submodules'])->pluck('slug')->all());

        $this->assertFalse($modules['warehouse_management']['subscribed']);
        $this->assertSame('inactive', $modules['warehouse_management']['status']);
        $this->assertContains('warehouses', collect($modules['warehouse_management']['submodules'])->pluck('slug')->all());
        $this->assertGreaterThan(0, $modules['warehouse_management']['feature_count']);
        $this->assertSame(1, $matrix['subscribed_module_count']);
        $this->assertSame(1, $matrix['unsubscribed_module_count']);
    }
}
