<?php

namespace App\Http\Middleware;

use App\Support\TenantRequestSignature;
use Closure;
use Illuminate\Http\Request;
use Modules\Tenancy\Models\Tenant;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenantContext
{
    public function __construct(
        private readonly Tenancy $tenancy,
        private readonly TenantRequestSignature $tenantRequestSignature
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        try {
            if ($request->is('api/internal/caddy/allow-domain')) {
                if ($this->tenancy->initialized) {
                    $this->tenancy->end();
                }

                return $next($request);
            }

            $host = $request->getHost();
            $centralDomains = collect(config('tenancy.central_domains', []))
                ->map(fn ($domain) => explode(':', (string) $domain)[0])
                ->filter()
                ->values()
                ->all();

            $tenantKey = $request->header('X-Tenant');
            $tenantFromHost = in_array($host, $centralDomains, true)
                ? null
                : $this->resolveTenant($host);
            $tenantFromHeader = $tenantKey ? $this->resolveTenant($tenantKey) : null;

            if ($tenantFromHost) {
                if ($tenantFromHeader && (string) $tenantFromHeader->id !== (string) $tenantFromHost->id) {
                    return $this->invalidTenantContextResponse(
                        'Tenant header does not match the resolved tenant host.',
                        'TENANT_HOST_HEADER_MISMATCH',
                        400
                    );
                }

                $tenant = $tenantFromHost;
            } elseif ($tenantKey) {
                if (!$tenantFromHeader) {
                    return $this->tenantNotFoundResponse($request);
                }

                if (
                    !$this->allowsUnsignedTenantHeader($request)
                    && !$this->tenantRequestSignature->matches(
                        (string) $tenantFromHeader->id,
                        $this->tenantRequestSignature->fromRequest($request)
                    )
                ) {
                    return $this->invalidTenantContextResponse(
                        'Tenant context signature is missing or invalid. Please authenticate again.',
                        'TENANT_CONTEXT_SIGNATURE_INVALID',
                        401
                    );
                }

                $tenant = $tenantFromHeader;
            } else {
                if ($this->tenancy->initialized) {
                    $this->tenancy->end();
                }

                return $next($request);
            }

            if (!$tenant) {
                return $this->tenantNotFoundResponse($request);
            }

            // Always initialize the tenant for the current request. Under Octane /
            // RoadRunner workers are reused, so skipping initialization when a
            // previous request already bootstrapped tenancy makes auth appear
            // random across requests.
            $this->tenancy->initialize($tenant);

            return $next($request);
        } catch (\Throwable $e) {
            \Log::error('Tenant initialization failed: ' . $e->getMessage(), [
                'exception' => $e,
                'host' => $request->getHost(),
                'tenant_header' => $request->header('X-Tenant'),
                'path' => $request->path()
            ]);

            return response()->json([
                'message' => 'The system could not establish a connection to your workspace database. Please contact support.',
                'error' => config('app.debug') ? $e->getMessage() : 'Database connection failed.',
                'code' => 'TENANT_INITIALIZATION_FAILED'
            ], 500);
        }
    }

    private function resolveTenant(string $tenantKey): ?Tenant
    {
        return Tenant::query()
            ->where('id', $tenantKey)
            ->orWhereHas('domains', fn ($query) => $query->where('domain', $tenantKey))
            ->first();
    }

    private function allowsUnsignedTenantHeader(Request $request): bool
    {
        return in_array(trim($request->path(), '/'), [
            'api/v1/login',
            'api/v1/tenant/login',
            'api/v1/verify-2fa',
            'api/v1/tenant/verify-2fa',
            'api/v1/password-policy',
            'api/v1/tenant/password-policy',
            'api/v1/reset-password',
            'api/v1/tenant/reset-password',
            'api/v1/broadcasting/auth',
        ], true);
    }

    private function tenantNotFoundResponse(Request $request): Response
    {
        if ($this->tenancy->initialized) {
            $this->tenancy->end();
        }

        $hasBearerToken = (bool) $request->bearerToken();

        return response()->json([
            'message' => $hasBearerToken
                ? 'Tenant context is no longer available. Please authenticate again.'
                : 'Tenant could not be identified for this request.',
            'code' => $hasBearerToken ? 'TENANT_CONTEXT_INVALID' : 'TENANT_NOT_FOUND',
        ], $hasBearerToken ? 401 : 404);
    }

    private function invalidTenantContextResponse(string $message, string $code, int $status): Response
    {
        if ($this->tenancy->initialized) {
            $this->tenancy->end();
        }

        return response()->json([
            'message' => $message,
            'code' => $code,
        ], $status);
    }
}
