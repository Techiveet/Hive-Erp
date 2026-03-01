<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorController extends Controller
{
    /**
     * ENABLE 2FA
     * Generates a secret and QR code (NOT confirmed yet)
     */
    public function enable(Request $request)
    {
        $user = $this->resolveUser($request);

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Prevent re-enabling if already confirmed
        if ($user->two_factor_confirmed_at) {
            return response()->json([
                'message' => 'Two-factor authentication is already enabled.'
            ], Response::HTTP_CONFLICT);
        }

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name', 'Hive ERP'),
            $user->email,
            $secret
        );

        return response()->json([
            'message' => '2FA secret generated. Scan the QR code to continue.',
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ], Response::HTTP_OK);
    }

    /**
     * CONFIRM 2FA
     * Verifies the OTP code and activates 2FA
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $this->resolveUser($request);

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$user->two_factor_secret) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled.'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $secret = decrypt($user->two_factor_secret);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Invalid or corrupted 2FA secret. Please re-enable 2FA.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $google2fa = new Google2FA();

        if (!$google2fa->verifyKey($secret, $request->code)) {
            return response()->json([
                'message' => 'Invalid authentication code.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $recoveryCodes = collect(range(1, 8))->map(function () {
            return Str::upper(Str::random(10)) . '-' . Str::upper(Str::random(10));
        })->values()->toArray();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ])->save();

        return response()->json([
            'message' => 'Two-factor authentication enabled successfully.',
            'recovery_codes' => $recoveryCodes,
        ], Response::HTTP_OK);
    }

    /**
     * DISABLE 2FA
     */
    public function destroy(Request $request)
    {
        $user = $this->resolveUser($request);

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$user->two_factor_secret) {
            return response()->json([
                'message' => 'Two-factor authentication is already disabled.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json([
            'message' => 'Two-factor authentication disabled successfully.'
        ], Response::HTTP_OK);
    }

    /**
     * Resolve user from Auth or user_id (API-friendly)
     */
    private function resolveUser(Request $request): ?User
    {
        return $request->user() ?? User::find($request->input('user_id'));
    }
}
