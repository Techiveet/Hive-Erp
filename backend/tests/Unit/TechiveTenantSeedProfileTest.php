<?php

namespace Tests\Unit;

use Modules\Subscription\Database\Seeders\TenantModuleSubscriptionsSeeder;
use Modules\Subscription\Support\TenantModuleCatalog;
use Modules\Tenancy\Database\Seeders\BusinessTypeSeeder;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantLandingTemplateCatalog;
use ReflectionClass;
use Tests\TestCase;

class TechiveTenantSeedProfileTest extends TestCase
{
    public function test_project_management_is_available_in_subscription_catalog(): void
    {
        $this->assertContains('project_management', TenantModuleCatalog::slugs());

        $normalized = TenantModuleCatalog::normalizeForStorage([
            'enabled_modules' => ['project_management'],
        ], 'business', 'database-seeder', 'software-development');

        $this->assertSame(['project_management'], $normalized['enabled_modules']);
    }

    public function test_software_development_business_type_is_seeded(): void
    {
        $seeder = new BusinessTypeSeeder(new TenantLandingTemplateCatalog());
        $reflection = new ReflectionClass($seeder);
        $definitions = $reflection->getMethod('definitions')->invoke($seeder);

        $definition = collect($definitions)->firstWhere('key', 'software-development');

        $this->assertIsArray($definition);
        $this->assertSame('Software Development', $definition['label']);
    }

    public function test_techive_seed_profile_unlocks_project_management_and_apps_tools_only(): void
    {
        $tenant = new Tenant([
            'id' => 'techive',
            'plan' => 'business',
            'business_type' => 'software-development',
        ]);

        $seeder = new TenantModuleSubscriptionsSeeder();
        $reflection = new ReflectionClass($seeder);
        $profile = $reflection->getMethod('subscriptionProfileFor')->invoke($seeder, $tenant);

        $this->assertSame([
            'project_management',
            'document_converter',
            'mailbox',
        ], $profile['enabled_modules']);

        $this->assertNotContains('api_docs', $profile['enabled_modules']);
        $this->assertNotContains('file_manager', $profile['enabled_modules']);
        $this->assertNotContains('media_library', $profile['enabled_modules']);
        $this->assertNotContains('workflow_automation', $profile['enabled_modules']);
    }
}
