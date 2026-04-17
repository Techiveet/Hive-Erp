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
        // If the Authorization header is missing but a token query parameter is provided,
        // inject it into the header so Sanctum/Passport can use it.
        if (!$request->hasHeader('Authorization') && $request->has('token')) {
            $request->headers->set('Authorization', 'Bearer ' . $request->query('token'));
        }

        return $next($request);
    }
}
