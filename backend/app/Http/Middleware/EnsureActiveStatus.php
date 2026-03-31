<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check if the user is deactivated
        if ($request->user() && !$request->user()->is_active) {
            // Revoke the token immediately
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'message' => 'CRITICAL: Your account has been deactivated by the administrator.'
            ], 403);
        }

        // 2. Check if the Tenant Node is suspended
        if (function_exists('tenant') && tenant()) {
            $tenantStatus = tenant()->getAttribute('is_active');

            // Treat missing/null status as active.
            // Only eject operators when the node is explicitly marked false.
            $tenantIsSuspended = filter_var($tenantStatus, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === false;

            if (!$tenantIsSuspended) {
                return $next($request);
            }

            // Revoke the token immediately
            if ($request->user()) {
                $request->user()->currentAccessToken()->delete();
            }

            return response()->json([
                'message' => 'CRITICAL: This network node has been suspended by Central Command.'
            ], 403);
        }

        return $next($request);
    }
}
