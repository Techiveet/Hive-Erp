<?php

namespace App\Support;

use Illuminate\Support\Str;
use RuntimeException;

class TemporaryDownloadSignature
{
    public function __construct(
        private readonly ?string $signingKey = null
    ) {
    }

    public function sign(array $payload): string
    {
        return hash_hmac('sha256', $this->canonicalPayload($payload), $this->normalizedKey());
    }

    public function matches(array $payload, ?string $signature): bool
    {
        if (!is_string($signature) || trim($signature) === '') {
            return false;
        }

        return hash_equals($this->sign($payload), trim($signature));
    }

    private function canonicalPayload(array $payload): string
    {
        ksort($payload);

        return collect($payload)
            ->map(fn ($value, $key) => "{$key}=".(string) $value)
            ->implode('&');
    }

    private function normalizedKey(): string
    {
        $key = (string) ($this->signingKey ?? config('app.key'));

        if ($key === '') {
            throw new RuntimeException('APP_KEY must be configured to sign temporary download URLs.');
        }

        if (Str::startsWith($key, 'base64:')) {
            $decoded = base64_decode(Str::after($key, 'base64:'), true);

            if ($decoded === false) {
                throw new RuntimeException('APP_KEY could not be decoded for temporary download signatures.');
            }

            return $decoded;
        }

        return $key;
    }
}
