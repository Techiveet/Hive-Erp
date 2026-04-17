<?php

namespace Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\AuthContext;
use App\Support\TenantRequestSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Modules\Core\Support\OwnedMediaPathResolver;
use Modules\Identity\Mail\UserCreated;
use Modules\Identity\Mail\UserStatusChanged;
use Modules\Identity\Mail\UserUpdated;
use Modules\Identity\Models\User;

class UserController extends Controller
{
    public function __construct(
        private readonly AuthContext $authContext,
        private readonly TenantRequestSignature $tenantRequestSignature,
        private readonly OwnedMediaPathResolver $ownedMediaPathResolver
    ) {
    }

    protected function success($data = null, $message = null, $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function error($message, $code = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $code);
    }

    private function resolveGuard(): string
    {
        return (function_exists('tenancy') && tenancy()->initialized) ? 'tenant' : 'web';
    }

    public function index(Request $request)
    {
        $isTenant = function_exists('tenant') && tenant('id');
        $tenantId = $isTenant ? tenant('id') : null;

        $search = $request->input('search', '');
        $status = $request->input('status', 'all');
        $role = $request->input('role', 'all');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if (!empty($search)) {
            $indexName = $isTenant ? "tenant_{$tenantId}_users" : 'central_users';
            $scout = User::search($search)->within($indexName);

            $scout->query(function ($query) use ($status, $role, $dateFrom, $dateTo) {
                $query->with('roles');
                $this->applyDatabaseFilters($query, $status, $role, $dateFrom, $dateTo);
            });

            $users = $scout->paginate($request->input('pageSize', 10));
            $engine = 'meilisearch';
        } else {
            $query = User::with('roles');
            $this->applyDatabaseFilters($query, $status, $role, $dateFrom, $dateTo);

            $query->orderByRaw('id = 1 DESC');

            $sortableColumns = ['id', 'name', 'email', 'is_active', 'created_at'];
            $sortCol = (string) $request->input('sort_by', 'created_at');
            $sortDir = strtolower((string) $request->input('sort_direction', 'desc'));

            if (!in_array($sortCol, $sortableColumns, true)) {
                $sortCol = 'created_at';
            }

            if (!in_array($sortDir, ['asc', 'desc'], true)) {
                $sortDir = 'desc';
            }

            if ($sortCol && $sortDir) {
                $query->orderBy($sortCol, $sortDir);
            } else {
                $query->orderBy('created_at', 'desc');
            }

            $users = $query->paginate($request->input('pageSize', 10));
            $engine = 'database';
        }

        $response = $users->toArray();
        $response['meta'] = [
            'engine' => $engine,
        ];

        return response()->json($response);
    }

    private function applyDatabaseFilters($query, $status, $role, $dateFrom, $dateTo)
    {
        if ($status !== 'all') {
            $query->where('is_active', $status === 'active');
        }

        if ($role !== 'all') {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        if (!empty($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
    }

    public function directory(Request $request)
    {
        $search = $request->input('search', '');
        $isTenant = function_exists('tenant') && tenant('id');

        if (!empty($search)) {
            $indexName = $isTenant ? 'tenant_' . tenant('id') . '_users' : 'central_users';
            $users = User::search($search)
                ->within($indexName)
                ->query(fn ($q) => $q->where('is_active', true))
                ->take(15)
                ->get();
        } else {
            $users = User::where('is_active', true)
                ->take(15)
                ->get();
        }

        $data = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|string|exists:roles,name',
            'avatar_path' => 'nullable|string',
            'password' => 'nullable|string|min:8',
        ]);

        if ($validated['role'] === 'Super Admin') {
            return $this->error('Access Denied: Cannot provision Super Admin accounts via API.', 403);
        }

        if ($request->filled('avatar_path')) {
            return $this->error('Avatar files must be uploaded by the target user after the account is created.', 422);
        }

        $rawPassword = $request->input('password') ?: Str::random(16);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($rawPassword),
            'is_active' => true,
            'avatar_path' => null,
        ]);

        $user->guard_name = $this->resolveGuard();
        $user->assignRole($validated['role']);

        Cache::forget("user_stats_{$user->guard_name}");

        try {
            $token = Password::createToken($user);
            $tenantId = function_exists('tenant') && tenant() ? tenant('id') : null;

            Mail::to($user->email)->queue(
                new UserCreated(
                    user: $user,
                    token: $token,
                    rawPassword: $rawPassword,
                    tenantId: $tenantId
                )
            );
        } catch (\Exception $e) {
            Log::warning('UserCreated email failed: ' . $e->getMessage());
        }

        return $this->success($user, 'Node operator provisioned successfully.', 201);
    }

    public function show($id)
    {
        $user = User::with('roles')->findOrFail($id);

        return $this->success($user);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->id === 1 || $user->id === '1') {
            return $this->error('The Core Overlord cannot be modified via API.', 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'role' => 'sometimes|string|exists:roles,name',
            'avatar_path' => 'nullable|string',
            'remove_avatar' => 'nullable|boolean',
            'password' => ['nullable', 'string', PasswordRule::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        if ($request->input('remove_avatar')) {
            $user->avatar_path = null;
        } elseif ($request->has('avatar_path')) {
            $avatarPath = trim((string) $request->input('avatar_path'));
            $actingUser = $request->user();
            $isPrivileged = method_exists($actingUser, 'hasAdministrativeRole') && $actingUser->hasAdministrativeRole();

            if (
                $avatarPath !== ''
                && !$isPrivileged
                && !$this->ownedMediaPathResolver->isOwnedMediaPath($avatarPath, (int) $user->id)
                && !$this->ownedMediaPathResolver->isOwnedMediaPath($avatarPath, (int) $actingUser->id)
            ) {
                return $this->error('Access Denied: The selected avatar is not owned by the target or the operator.', 422);
            }

            $user->avatar_path = $avatarPath !== '' ? $avatarPath : null;
        }

        $userData = $request->only(['name', 'email']);

        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->input('password'));
            Log::info("Password manually updated for user ID: {$user->id}");
        }

        $user->update($userData);
        $user->save();

        if ($request->has('role')) {
            $user->guard_name = $this->resolveGuard();
            $user->syncRoles([$request->role]);
        }

        try {
            if ($user->wasChanged()) {
                $changes = $user->getChanges();
                unset($changes['password']);

                if ($request->filled('password')) {
                    $changes['password_status'] = 'updated';
                }

                Mail::to($user->email)->queue(new UserUpdated($user, $changes));
            }
        } catch (\Exception $e) {
            Log::warning('UserUpdated email failed: ' . $e->getMessage());
        }

        return $this->success($user, 'Operator record updated.');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if ($user->id === 1 || $user->id === '1') {
            return $this->error('CRITICAL: Cannot delete the Core Overlord.', 403);
        }

        if ($user->avatar_path) {
            $avatarMedia = $this->ownedMediaPathResolver->resolveOwnedMediaFromPath($user->avatar_path, (int) $user->id);

            if ($avatarMedia) {
                $avatarMedia->delete();
            } else {
                Storage::disk('public')->delete(ltrim(str_replace('/storage/', '', $user->avatar_path), '/'));
            }
        }

        $guard = $this->resolveGuard();
        $user->delete();

        Cache::forget("user_stats_{$guard}");

        return $this->success(null, 'Operator purged from the network.');
    }

    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);

        if ($user->id === 1 || $user->id === '1') {
            return $this->error('The Core Overlord cannot be deactivated.', 403);
        }

        $user->update(['is_active' => !$user->is_active]);

        Cache::forget("user_stats_{$this->resolveGuard()}");

        try {
            Mail::to($user->email)->queue(new UserStatusChanged($user));
        } catch (\Exception $e) {
            Log::warning('UserStatusChanged email failed: ' . $e->getMessage());
        }

        $status = $user->is_active ? 'activated' : 'deactivated';

        return $this->success($user, "Operator successfully {$status}.");
    }

    public function impersonate(Request $request, $id)
    {
        try {
            if (!$request->user()) {
                return $this->error('Authentication required.', 401);
            }

            $currentUserId = $request->user()->id;
            $identityUser = User::find($currentUserId);

            if (!$identityUser || !$identityUser->isSuperAdmin()) {
                return $this->error('CRITICAL: Insufficient clearance. Only Super Admins can impersonate users.', 403);
            }

            $userToImpersonate = User::findOrFail($id);

            if ($userToImpersonate->id === 1 || $userToImpersonate->id === '1') {
                return $this->error('Cannot impersonate the Core Overlord.', 403);
            }

            $currentContext = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';
            $token = $userToImpersonate->createToken(
                'impersonation_token',
                $this->authContext->abilitiesFor($currentContext)
            )->plainTextToken;

            return $this->success([
                'token' => $token,
                'user' => $userToImpersonate,
                'context' => $currentContext,
                'context_signature' => $currentContext === 'central'
                    ? null
                    : $this->tenantRequestSignature->sign($currentContext),
            ], 'Impersonation sequence activated.');
        } catch (\Exception $e) {
            Log::error('Impersonation Error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'target_user_id' => $id,
            ]);

            return $this->error('Impersonation sequence failed.', 500);
        }
    }
}
