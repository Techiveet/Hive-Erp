<?php

namespace Modules\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireBusinessType
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$businessTypes): Response
    {
        /** @var \Modules\Tenancy\Models\Tenant|null $tenant */
        $tenant = tenant();

        if (!$tenant) {
            abort(403, 'Tenant context is missing.');
        }

        $currentBusinessType = $tenant->business_type;

        if (empty($businessTypes)) {
            return $next($request);
        }

        if (!in_array($currentBusinessType, $businessTypes, true)) {
            abort(403, 'This action is unsupported for your current business type.');
        }

        return $next($request);
    }
}
