<?php

namespace App\Support;

use Illuminate\Http\Request;

class AuthContext
{
    public function current(): string
    {
        return function_exists('tenant') && tenant('id')
            ? (string) tenant('id')
            : 'central';
    }

    public function ability(?string $context = null): string
    {
        return 'context:' . ($context ?: $this->current());
    }

    public function abilitiesFor(?string $context = null): array
    {
        return [$this->ability($context)];
    }

    public function tokenMatchesRequest(Request $request): bool
    {
        $token = $request->user()?->currentAccessToken();

        if (!$token) {
            return false;
        }

        return in_array($this->ability(), $token->abilities ?? [], true);
    }
}
