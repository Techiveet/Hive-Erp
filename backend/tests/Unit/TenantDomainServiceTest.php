<?php

namespace Tests\Unit;

use Modules\Tenancy\Support\TenantDomainService;
use PHPUnit\Framework\TestCase;

class TenantDomainServiceTest extends TestCase
{
    public function test_normalize_domain_returns_empty_string_for_empty_input(): void
    {
        $service = new TenantDomainService();

        $this->assertSame('', $service->normalizeDomain(''));
        $this->assertSame('', $service->normalizeDomain('https://'));
        $this->assertSame('', $service->normalizeDomain('   '));
    }

    public function test_normalize_domain_strips_scheme_path_and_port(): void
    {
        $service = new TenantDomainService();

        $this->assertSame('hive.example.com', $service->normalizeDomain('https://Hive.Example.com:3000/dashboard'));
    }
}
