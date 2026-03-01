<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

// Aliased to prevent naming conflicts
use Illuminate\Support\Facades\Password as PasswordFacade;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    /**
     * Get the authenticated user's profile with fresh roles & permissions
     */
    public function user(Request $request)
    {
        $user = $request->user();

        return response()->json([
            // Return exactly what the frontend expects to update local storage
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'roles'       => $user->getRoleNames(), // 🚀 Fresh Spatie Roles
            'permissions' => $user->getAllPermissions()->pluck('name'), // 🚀 Fresh Spatie Permissions
        ], 200);
    }
    /**
     * Handle user authentication
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'This account has been deactivated.'], 403);
        }

        // Handle 2FA Redirection
        if ($user->two_factor_confirmed_at) {
            $tempToken = Str::random(64);
            Cache::put('2fa_auth_' . $tempToken, $user->id, now()->addMinutes(10));

            return response()->json([
                'message' => 'Verification required.',
                'data' => [
                    'requires_2fa'     => true,
                    'two_factor_token' => $tempToken,
                ]
            ], 200);
        }

        // Normal Login (No 2FA)
        $token = $user->createToken('hive-access-token')->plainTextToken;

        return response()->json([
            'message' => 'Authentication successful.',
            'data' => [
                'user' => [
                    'id'          => $user->id,
                    'name'        => $user->name,
                    'email'       => $user->email,
                    'roles'       => $user->getRoleNames(), // 🚀 Standard Spatie roles
                    'permissions' => $user->getAllPermissions()->pluck('name'), // 🚀 Standard Spatie permissions
                ],
                'token'   => $token,
                'context' => tenancy()->initialized ? 'tenant' : 'central'
            ]
        ], 200);
    }

    /**
     * Verify 2FA code
     */
    public function verify2FA(Request $request)
    {
        $request->validate([
            'two_factor_token' => 'required|string',
            'code'             => 'required|string|size:6',
        ]);

        $userId = Cache::pull('2fa_auth_' . $request->two_factor_token);

        if (!$userId) {
            return response()->json(['message' => 'The 2FA session has expired.'], 401);
        }

        $user = User::find($userId);

        if (!$user || !$user->two_factor_confirmed_at || !$user->two_factor_secret) {
            return response()->json(['message' => '2FA is not configured.'], 400);
        }

        $google2fa = new Google2FA();

        try {
            $secret = decrypt($user->two_factor_secret);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Security error: Invalid secret.'], 500);
        }

        if (!$google2fa->verifyKey($secret, $request->code)) {
            return response()->json(['message' => 'Invalid authentication code.'], 401);
        }

        $token = $user->createToken('hive-access-token')->plainTextToken;

        return response()->json([
            'message' => '2FA verification successful.',
            'data' => [
                'user' => [
                    'id'          => $user->id,
                    'name'        => $user->name,
                    'email'       => $user->email,
                    'roles'       => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ],
                'token'   => $token,
                'context' => tenancy()->initialized ? 'tenant' : 'central'
            ]
        ], 200);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.'], 200);
    }

    /**
     * Password Policy Exposure
     */
    public function passwordPolicy()
    {
        return response()->json([
            'min_length' => 8,
            'require_mixed_case' => true,
            'require_numbers' => true,
            'require_symbols' => true,
        ], 200);
    }

    /**
     * Reset user password
     */
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

        $status = PasswordFacade::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->save();
            }
        );

        if ($status === PasswordFacade::PASSWORD_RESET) {
            return response()->json(['message' => 'Encryption key updated.'], 200);
        }

        return response()->json(['message' => 'Invalid or expired security token.'], 400);
    }
}
