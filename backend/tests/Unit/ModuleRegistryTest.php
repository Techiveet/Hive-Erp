<?php

namespace Tests\Unit;

use Modules\Core\Support\ModuleRegistry;
use PHPUnit\Framework\TestCase;

class ModuleRegistryTest extends TestCase
{
    public function test_declared_dependencies_are_known_and_acyclic(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/modular_monolith.php';
        $registry = new ModuleRegistry($config['modules']);

        $this->assertSame([], $registry->validateDependencies());
    }

    public function test_each_module_exposes_public_api_and_frontend_contract_metadata(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/modular_monolith.php';
        $registry = new ModuleRegistry($config['modules']);

        foreach ($registry->all() as $key => $module) {
            $this->assertNotEmpty($module['public_api']['http_prefixes'] ?? [], "Module [{$key}] is missing public API prefixes.");
            $this->assertNotEmpty($module['frontend_contract']['version'] ?? null, "Module [{$key}] is missing a frontend contract version.");
        }
    }
}
