<?php
/**
 * Created At: 2026-04-17
 * Purpose: Allows authenticating API requests via a "token" query parameter.
 * This is primarily used for direct browser downloads where setting an Authorization header is not possible.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleQueryToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Backward compatibility only for legacy media stream URLs.
        $isLegacyMediaStream = str_contains($request->path(), 'api/v1/media/stream/');

        if (
            $isLegacyMediaStream
            && !$request->hasHeader('Authorization')
            && $request->has('token')
        ) {
            $request->headers->set('Authorization', 'Bearer ' . $request->query('token'));
        }

        return $next($request);
    }
}
