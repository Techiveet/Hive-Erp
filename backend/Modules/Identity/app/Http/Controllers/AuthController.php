<?php

namespace Modules\Identity\Http\Controllers;

use App\Support\AuthContext;
use App\Support\TenantRequestSignature;
use App\Http\Controllers\Controller;
use Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Subscription\Support\TenantModuleCatalog;
use Modules\Subscription\Support\TenantSubscriptionService;
use PragmaRX\Google2FA\Google2FA;
use Stevebauman\Location\Facades\Location;
use Spatie\Permission\PermissionRegistrar;

// Aliased to prevent naming conflicts
use Illuminate\Support\Facades\Password as PasswordFacade;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthContext $authContext,
        private readonly TenantRequestSignature $tenantRequestSignature
    ) {
    }

    /**
     * Retrieve the authenticated operator's profile.
     */
    public function user(Request $request)
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $user = $request->user();

        return response()->json($this->formatUserPayload($user, function_exists('tenant') && tenant('id') ? tenant('id') : 'central'), 200);
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
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);
        $email = strtolower((string) $request->input('email'));

        if ($this->isTemporarilyLocked('login', $request, $email)) {
            return response()->json(['message' => 'Too many failed attempts. Try again in a few minutes.'], 423);
        }

        $user = User::where('email', $email)->first();
        $currentTenant = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';

        // 🛡️ LOG: FAILED LOGIN
        if (!$user || !Hash::check($request->password, $user->password)) {
            $this->recordFailedAttempt('login', $request, $email, 5, 300);
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
        $this->clearFailedAttempts('login', $request, $email);

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
        $token = $user->createToken(
            'hive-access-token',
            $this->authContext->abilitiesFor($currentTenant)
        )->plainTextToken;

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
                'user' => $this->formatUserPayload($user, $currentTenant),
                'token' => $token,
                'context' => $currentTenant,
                'context_signature' => $currentTenant === 'central'
                    ? null
                    : $this->tenantRequestSignature->sign($currentTenant),
            ]
        ], 200);
    }

    /**
     * Verify the 6-digit cryptographic code to finalize the 2FA handshake.
     */
    public function verify2FA(Request $request)
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $request->validate([
            'email'            => 'required|email',
            'two_factor_token' => 'required|string',
            'code'             => 'required|string',
        ]);
        $email = strtolower((string) $request->input('email'));

        if ($this->isTemporarilyLocked('2fa', $request, $email)) {
            return response()->json(['message' => 'Too many invalid codes. Try again in a few minutes.'], 423);
        }

        $currentTenant = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';
        $userId = Cache::get('2fa_auth_' . $request->two_factor_token);

        if (!$userId) {
            return response()->json(['message' => '2FA session expired. Please log in again.'], 401);
        }

        $user = User::find($userId);

        if (!$user || $user->email !== $request->email) {
            $this->recordFailedAttempt('2fa', $request, $email, 6, 300);
            return response()->json(['message' => 'Invalid session data.'], 401);
        }

        try {
            $secret = decrypt($user->two_factor_secret);
            $google2fa = new Google2FA();

            if (!$google2fa->verifyKey($secret, $request->code)) {
                $this->recordFailedAttempt('2fa', $request, $email, 6, 300);
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
            $this->clearFailedAttempts('2fa', $request, $email);

            if (is_null($user->two_factor_confirmed_at)) {
                $user->two_factor_confirmed_at = now();
                $user->save();
            }

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to process 2FA.'], 500);
        }

        Cache::forget('2fa_auth_' . $request->two_factor_token);
        $token = $user->createToken(
            'hive-access-token',
            $this->authContext->abilitiesFor($currentTenant)
        )->plainTextToken;

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
                'user' => $this->formatUserPayload($user, $currentTenant, true),
                'token' => $token,
                'context' => $currentTenant,
                'context_signature' => $currentTenant === 'central'
                    ? null
                    : $this->tenantRequestSignature->sign($currentTenant),
            ]
        ], 200);
    }

    private function isTemporarilyLocked(string $scope, Request $request, string $email): bool
    {
        $keys = $this->attemptKeys($scope, $request, $email);
        $ipUntil = (int) Cache::get("{$keys['ip']}:locked_until", 0);
        $emailUntil = (int) Cache::get("{$keys['email']}:locked_until", 0);

        return $ipUntil > now()->timestamp || $emailUntil > now()->timestamp;
    }

    private function recordFailedAttempt(string $scope, Request $request, string $email, int $threshold, int $lockSeconds): void
    {
        $keys = $this->attemptKeys($scope, $request, $email);
        $ttl = now()->addSeconds($lockSeconds);

        foreach (['ip', 'email'] as $type) {
            $attemptKey = "{$keys[$type]}:attempts";
            $attempts = (int) Cache::increment($attemptKey);
            Cache::put($attemptKey, $attempts, $ttl);

            if ($attempts >= $threshold) {
                Cache::put("{$keys[$type]}:locked_until", now()->addSeconds($lockSeconds)->timestamp, $ttl);
            }
        }
    }

    private function clearFailedAttempts(string $scope, Request $request, string $email): void
    {
        $keys = $this->attemptKeys($scope, $request, $email);
        foreach (['ip', 'email'] as $type) {
            Cache::forget("{$keys[$type]}:attempts");
            Cache::forget("{$keys[$type]}:locked_until");
        }
    }

    private function attemptKeys(string $scope, Request $request, string $email): array
    {
        $normalizedEmail = strtolower(trim($email ?: 'unknown'));
        return [
            'ip' => "auth_lock:{$scope}:ip:".$request->ip(),
            'email' => "auth_lock:{$scope}:email:{$normalizedEmail}",
        ];
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

            $user->currentAccessToken()?->delete();
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

    private function formatUserPayload(User $user, string $currentTenant, ?bool $twoFactorEnabled = null): array
    {
        /** @var \Modules\Tenancy\Models\Tenant|null $tenant */
        $tenant = function_exists('tenant') ? tenant() : null;
        \Log::info('Auth Payload Tenant', ['id' => $tenant?->id, 'type' => $tenant?->business_type]);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'central_control_override' => $user->hasCentralControlOverride(),
            'has_completed_welcome_tour' => (bool) $user->has_completed_welcome_tour,
            'two_factor_enabled' => $twoFactorEnabled ?? $user->two_factor_enabled,
            'business_type' => $tenant?->business_type ?? ($currentTenant !== 'central' ? \DB::table('tenants')->where('id', $currentTenant)->value('business_type') : null),
            'module_access' => $this->resolveModuleAccess($tenant),
        ];
    }

    private function resolveModuleAccess(?\Modules\Tenancy\Models\Tenant $tenant): array
    {
        if (!$tenant) {
            return [
                'plan' => 'central',
                'subscription_status' => 'active',
                'bypass_checks' => true,
                'active_modules' => TenantModuleCatalog::slugs(),
                'statuses' => collect(TenantModuleCatalog::catalog())
                    ->mapWithKeys(fn (array $module) => [
                        $module['slug'] => [
                            'active' => true,
                            'included_in_plan' => true,
                            'name' => $module['name'],
                            'monthly_price_etb' => (float) ($module['monthly_price_etb'] ?? 0),
                        ],
                    ])
                    ->all(),
            ];
        }

        try {
            return app(TenantSubscriptionService::class)->buildModuleAccess($tenant);
        } catch (\Throwable $e) {
            \Log::warning('Failed to resolve module access for tenant ' . $tenant->id . ': ' . $e->getMessage());

            // Return a safe fallback so the user can still log in
            return [
                'plan' => 'fallback',
                'subscription_status' => 'active',
                'active_modules' => TenantModuleCatalog::slugs(),
                'error' => 'Subscription service unavailable',
            ];
        }
    }
}
