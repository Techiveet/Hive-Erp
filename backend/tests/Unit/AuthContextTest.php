<?php

namespace Tests\Unit;

use App\Support\AuthContext;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class AuthContextTest extends TestCase
{
    public function test_it_builds_explicit_context_abilities(): void
    {
        $context = new AuthContext();

        $this->assertSame('context:central', $context->ability());
        $this->assertSame(['context:tenant-a'], $context->abilitiesFor('tenant-a'));
    }

    public function test_it_requires_an_explicit_context_ability_on_the_token(): void
    {
        $context = new AuthContext();

        $matchingRequest = Request::create('/');
        $matchingRequest->setUserResolver(fn () => $this->fakeUserWithAbilities(['context:central']));
        $this->assertTrue($context->tokenMatchesRequest($matchingRequest));

        $wildcardRequest = Request::create('/');
        $wildcardRequest->setUserResolver(fn () => $this->fakeUserWithAbilities(['*']));
        $this->assertFalse($context->tokenMatchesRequest($wildcardRequest));
    }

    private function fakeUserWithAbilities(array $abilities): object
    {
        $token = (object) ['abilities' => $abilities];

        return new class($token)
        {
            public function __construct(
                private readonly object $token
            ) {
            }

            public function currentAccessToken(): object
            {
                return $this->token;
            }
        };
    }
}
