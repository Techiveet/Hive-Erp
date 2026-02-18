<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA; // Ensure this is imported

class AuthController extends SpeedController
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->error('Invalid credentials.', 401);
        }

        if (! $user->is_active) {
            return $this->error('This account has been deactivated.', 403);
        }

        // Check if 2FA is required
        if ($user->two_factor_confirmed_at) {
            return $this->success([
                'requires_2fa' => true,
                'user_id'      => $user->id,
            ], 'Verification required.');
        }

        $token = $user->createToken('hive-access-token')->plainTextToken;

        return $this->success([
            'user'    => $user,
            'token'   => $token,
            'context' => tenancy()->initialized ? 'tenant' : 'central'
        ], 'Authentication successful.');
    }

    /**
     * ✅ NEW: Verify OTP and Issue Token
     */
    public function verify2FA(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'code'    => 'required|string', // The 6-digit OTP
        ]);

        $user = User::findOrFail($request->user_id);

        // Security: Ensure 2FA is actually enabled for this user
        if (! $user->two_factor_confirmed_at || ! $user->two_factor_secret) {
            return $this->error('2FA is not enabled for this account.', 400);
        }

        $google2fa = new Google2FA();

        // Decrypt the secret (Must match encryption in TwoFactorController)
        try {
            $secret = decrypt($user->two_factor_secret);
        } catch (\Exception $e) {
            return $this->error('Security error: Invalid 2FA secret.', 500);
        }

        // Verify the code
        // verifyKey(secret, code, window) - window allows for slight time drift
        $valid = $google2fa->verifyKey($secret, $request->code);

        if (! $valid) {
            return $this->error('Invalid authentication code.', 401);
        }

        // 🔓 SUCCESS: Issue the Token
        $token = $user->createToken('hive-access-token')->plainTextToken;

        return $this->success([
            'user'    => $user,
            'token'   => $token,
            'context' => tenancy()->initialized ? 'tenant' : 'central'
        ], '2FA verification successful.');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success(null, 'Logged out successfully.');
    }
}
