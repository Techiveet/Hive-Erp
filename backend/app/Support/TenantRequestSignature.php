<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class TenantRequestSignature
{
    public function __construct(
        private readonly ?string $signingKey = null
    ) {
    }

    public function sign(string $tenantId): string
    {
        return hash_hmac('sha256', $tenantId, $this->normalizedKey());
    }

    public function matches(?string $tenantId, ?string $signature): bool
    {
        if (!$tenantId || !$signature) {
            return false;
        }

        return hash_equals($this->sign($tenantId), trim($signature));
    }

    public function fromRequest(Request $request): ?string
    {
        $signature = $request->header('X-Tenant-Signature');

        if (!is_string($signature)) {
            return null;
        }

        $signature = trim($signature);

        return $signature !== '' ? $signature : null;
    }

    private function normalizedKey(): string
    {
        $key = (string) ($this->signingKey ?? config('app.key'));

        if ($key === '') {
            throw new RuntimeException('APP_KEY must be configured to sign tenant context headers.');
        }

        if (Str::startsWith($key, 'base64:')) {
            $decoded = base64_decode(Str::after($key, 'base64:'), true);

            if ($decoded === false) {
                throw new RuntimeException('APP_KEY could not be decoded for tenant context signing.');
            }

            return $decoded;
        }

        return $key;
    }
}
