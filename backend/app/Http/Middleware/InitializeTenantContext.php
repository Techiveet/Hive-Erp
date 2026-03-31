<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Tenancy\Models\Tenant;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenantContext
{
    public function __construct(
        private readonly Tenancy $tenancy
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $centralDomains = collect(config('tenancy.central_domains', []))
            ->map(fn ($domain) => explode(':', (string) $domain)[0])
            ->filter()
            ->values()
            ->all();

        $tenantKey = $request->header('X-Tenant') ?: $request->query('tenant');
        $tenant = null;

        if ($tenantKey) {
            $tenant = Tenant::query()
                ->where('id', $tenantKey)
                ->orWhereHas('domains', fn ($query) => $query->where('domain', $tenantKey))
                ->first();
        } else {
            if (in_array($host, $centralDomains, true)) {
                if ($this->tenancy->initialized) {
                    $this->tenancy->end();
                }

                return $next($request);
            }

            $tenant = Tenant::query()
                ->whereHas('domains', fn ($query) => $query->where('domain', $host))
                ->first();
        }

        if (!$tenant) {
            if ($this->tenancy->initialized) {
                $this->tenancy->end();
            }

            return response()->json([
                'message' => 'Tenant could not be identified for this request.',
            ], 404);
        }

        // Always initialize the tenant for the current request. Under Octane /
        // RoadRunner workers are reused, so skipping initialization when a
        // previous request already bootstrapped tenancy makes auth appear
        // random across requests.
        $this->tenancy->initialize($tenant);

        return $next($request);
    }
}
