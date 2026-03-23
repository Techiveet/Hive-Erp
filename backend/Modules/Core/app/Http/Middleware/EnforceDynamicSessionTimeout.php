<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnforceDynamicSessionTimeout
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Only run this check if the user is currently authenticated
        $user = $request->user();

        if ($user && $token = $user->currentAccessToken()) {

            // 🚀 1. Fetch the dynamic timeout limit from our global helper
            // We use 120 as a safe fallback if the setting isn't found
            $timeoutMinutes = (int) get_system_setting('session_timeout_minutes', 120);

            // 🚀 2. Check if the token has been idle longer than the allowed time
            if ($token->last_used_at && now()->diffInMinutes($token->last_used_at) > $timeoutMinutes) {

                // 🛡️ Log the auto-logout to your WORM audit ledger
                $currentTenant = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';

                activity('Security & Access')
                    ->causedBy($user)
                    ->tap(function($activity) use ($currentTenant) { $activity->tenant_id = $currentTenant; })
                    ->withProperties([
                        'ip' => $request->ip(),
                        'reason' => 'Backend enforced idle timeout limit (' . $timeoutMinutes . ' mins)'
                    ])
                    ->log('auto_logged_out');

                // 🚀 3. Destroy the token to completely secure the backend
                $token->delete();

                // 4. Return a 401 to force the frontend to redirect to login
                return response()->json([
                    'message' => 'Session expired due to inactivity. Please authenticate again.',
                    'code' => 'SESSION_EXPIRED'
                ], 401);
            }
        }

        return $next($request);
    }
}
