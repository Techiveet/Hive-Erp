<?php

namespace Tests\Unit;

use App\Support\TenantRequestSignature;
use PHPUnit\Framework\TestCase;

class TenantRequestSignatureTest extends TestCase
{
    public function test_it_creates_stable_signatures_for_a_tenant_context(): void
    {
        $signature = new TenantRequestSignature('base64:' . base64_encode('tenant-signature-test-key'));
        $signed = $signature->sign('tenant-alpha');

        $this->assertNotSame('', $signed);
        $this->assertTrue($signature->matches('tenant-alpha', $signed));
        $this->assertFalse($signature->matches('tenant-beta', $signed));
    }
}
