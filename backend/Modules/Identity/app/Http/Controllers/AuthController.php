<?php

namespace Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Stevebauman\Location\Facades\Location;

// Aliased to prevent naming conflicts
use Illuminate\Support\Facades\Password as PasswordFacade;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    /**
     * Retrieve the authenticated operator's profile.
     */
    public function user(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id'                 => $user->id,
            'name'               => $user->name,
            'email'              => $user->email,
            'roles'              => $user->getRoleNames(),
            'permissions'        => $user->getAllPermissions()->pluck('name'),
            'two_factor_enabled' => !empty($user->two_factor_secret) && $user->two_factor_confirmed_at !== null,
        ], 200);
    }

    /**
     * Lightweight heartbeat endpoint to keep the session token alive.
     */
    public function ping(Request $request)
    {
        return response()->json([
            'status' => 'alive',
            'timestamp' => now()->toIso8601String()
        ], 200);
    }

    /**
     * Authenticate an operator and initiate the handshake protocol.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();
        $currentTenant = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';

        // 🛡️ LOG: FAILED LOGIN
        if (!$user || !Hash::check($request->password, $user->password)) {
            activity('Security & Access')
                ->tap(function($activity) use ($currentTenant) { $activity->tenant_id = $currentTenant; })
                ->withProperties([
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'email_used' => $request->email,
                    'status' => 'failed'
                ])
                ->log('login_failed');

            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        // 🛡️ LOG: DEACTIVATED ACCOUNT ATTEMPT
        if (!$user->is_active) {
            activity('Security & Access')
                ->causedBy($user)
                ->tap(function($activity) use ($currentTenant) { $activity->tenant_id = $currentTenant; })
                ->withProperties([
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'status' => 'blocked_deactivated'
                ])
                ->log('login_blocked');

            return response()->json(['message' => 'This account has been deactivated.'], 403);
        }

        // 🚀 1. CHECK GLOBAL AND PERSONAL 2FA STATUS
        $global2faEnforced = get_system_setting('require_2fa', false);
        $hasSecret = !empty($user->two_factor_secret);
        $isConfirmed = $user->two_factor_confirmed_at !== null;

        // 🚀 2. INTERCEPT IF 2FA IS REQUIRED
        if ($global2faEnforced || ($hasSecret && $isConfirmed)) {

            $tempToken = Str::random(64);
            Cache::put('2fa_auth_' . $tempToken, $user->id, now()->addMinutes(15));

            activity('Security & Access')
                ->causedBy($user)
                ->tap(function($activity) use ($currentTenant) { $activity->tenant_id = $currentTenant; })
                ->withProperties([
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'status' => 'pending_2fa'
                ])
                ->log('login_2fa_initiated');

            $response = [
                'message'             => 'Verification required.',
                'requires_2fa'        => true,
                'two_factor_token'    => $tempToken,
                'global_2fa_enforced' => $global2faEnforced,
            ];

            // 🚀 3. FORCED SETUP LOGIC
            if ($global2faEnforced && (!$hasSecret || !$isConfirmed)) {
                $google2fa = new Google2FA();

                if (!$hasSecret) {
                    $rawSecret = $google2fa->generateSecretKey();
                    $user->two_factor_secret = encrypt($rawSecret);
                    $user->save();
                } else {
                    $rawSecret = decrypt($user->two_factor_secret);
                }

                $appName = get_system_setting('system_email_name', 'HIVE.OS');
                $qrCodeUrl = $google2fa->getQRCodeUrl($appName, $user->email, $rawSecret);

                $response['requires_2fa_setup'] = true;
                $response['qr_code_url'] = $qrCodeUrl;
                $response['secret'] = $rawSecret;
            }

            return response()->json($response, 200);
        }

        // 🚀 4. NORMAL LOGIN (No 2FA Required)
        $token = $user->createToken('hive-access-token')->plainTextToken;

        // TRACK GEOGRAPHIC LOCATION
        $this->recordLoginHistory($user, $request, $currentTenant);

        activity('Security & Access')
            ->causedBy($user)
            ->tap(function($activity) use ($currentTenant) { $activity->tenant_id = $currentTenant; })
            ->withProperties([
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'auth_type' => 'password',
                'status' => 'success'
            ])
            ->log('logged_in');

        return response()->json([
            'message' => 'Authentication successful.',
            'data' => [
                'user' => [
                    'id'                 => $user->id,
                    'name'               => $user->name,
                    'email'              => $user->email,
                    'roles'              => $user->getRoleNames(),
                    'permissions'        => $user->getAllPermissions()->pluck('name'),
                    'two_factor_enabled' => false,
                ],
                'token'   => $token,
                'context' => $currentTenant
            ]
        ], 200);
    }

    /**
     * Verify the 6-digit cryptographic code to finalize the 2FA handshake.
     */
    public function verify2FA(Request $request)
    {
        $request->validate([
            'email'            => 'required|email',
            'two_factor_token' => 'required|string',
            'code'             => 'required|string',
        ]);

        $currentTenant = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';
        $userId = Cache::get('2fa_auth_' . $request->two_factor_token);

        if (!$userId) {
            return response()->json(['message' => '2FA session expired. Please log in again.'], 401);
        }

        $user = User::find($userId);

        if (!$user || $user->email !== $request->email) {
            return response()->json(['message' => 'Invalid session data.'], 401);
        }

        try {
            $secret = decrypt($user->two_factor_secret);
            $google2fa = new Google2FA();

            if (!$google2fa->verifyKey($secret, $request->code)) {
                activity('Security & Access')
                    ->causedBy($user)
                    ->tap(function($activity) use ($currentTenant) { $activity->tenant_id = $currentTenant; })
                    ->withProperties([
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'status' => 'failed_2fa'
                    ])
                    ->log('login_2fa_failed');

                return response()->json(['message' => 'Invalid authentication code.'], 401);
            }

            if (is_null($user->two_factor_confirmed_at)) {
                $user->two_factor_confirmed_at = now();
                $user->save();
            }

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to process 2FA.'], 500);
        }

        Cache::forget('2fa_auth_' . $request->two_factor_token);
        $token = $user->createToken('hive-access-token')->plainTextToken;

        // TRACK GEOGRAPHIC LOCATION
        $this->recordLoginHistory($user, $request, $currentTenant);

        activity('Security & Access')
            ->causedBy($user)
            ->tap(function($activity) use ($currentTenant) { $activity->tenant_id = $currentTenant; })
            ->withProperties([
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'auth_type' => '2fa',
                'status' => 'success'
            ])
            ->log('logged_in');

        return response()->json([
            'message' => 'Authentication successful.',
            'data' => [
                'user' => [
                    'id'                 => $user->id,
                    'name'               => $user->name,
                    'email'              => $user->email,
                    'roles'              => $user->getRoleNames(),
                    'permissions'        => $user->getAllPermissions()->pluck('name'),
                    'two_factor_enabled' => true,
                ],
                'token'   => $token,
                'context' => $currentTenant
            ]
        ], 200);
    }

    /**
     * Terminate the operator's active session and revoke the access token.
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        $currentTenant = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';

        if ($user) {
            activity('Security & Access')
                ->causedBy($user)
                ->tap(function($activity) use ($currentTenant) { $activity->tenant_id = $currentTenant; })
                ->withProperties([
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ])
                ->log('logged_out');

            $user->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Logged out successfully.'], 200);
    }

    public function passwordPolicy()
    {
        return response()->json([
            'min_length'         => 8,
            'require_mixed_case' => true,
            'require_numbers'    => true,
            'require_symbols'    => true,
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8)->mixedCase()->numbers()->symbols()
            ],
        ]);

        $currentTenant = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';

        $status = PasswordFacade::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->save();
            }
        );

        if ($status === PasswordFacade::PASSWORD_RESET) {
            $user = User::where('email', $request->email)->first();
            if ($user) {
                activity('Security & Access')
                    ->causedBy($user)
                    ->tap(function($activity) use ($currentTenant) { $activity->tenant_id = $currentTenant; })
                    ->withProperties([
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ])
                    ->log('password_reset');
            }

            return response()->json(['message' => 'Encryption key updated.'], 200);
        }

        return response()->json(['message' => 'Invalid or expired security token.'], 400);
    }

    /**
     * 🚀 Helper: Extract and log the Geographic location of a successful login
     */
    private function recordLoginHistory($user, Request $request, $currentTenant)
    {
        $ip = $request->ip();

        // 🚀 UNCOMMENT THIS LINE FOR LOCAL TESTING TO GET AN ADDIS ABABA IP
        // $ip = '72.229.28.185';

        $cityName = 'Unknown Server';
        $countryCode = null;

        // Try to locate the IP via MaxMind/IP-API
        if ($position = Location::get($ip)) {
            $cityName = $position->cityName ?: 'Unknown Server';
            $countryCode = $position->countryCode;
        }

        DB::table('login_histories')->insert([
            'user_id' => $user->id,
            'tenant_id' => $currentTenant === 'central' ? null : $currentTenant,
            'ip_address' => $ip,
            'city' => $cityName,
            'country_code' => $countryCode,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
