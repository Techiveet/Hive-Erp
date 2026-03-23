<?php

namespace Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

use Modules\Identity\Mail\UserCreated;
use Modules\Identity\Mail\UserUpdated;
use Modules\Identity\Mail\UserStatusChanged;

class UserController extends Controller
{
    protected function success($data = null, $message = null, $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
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
            $indexName = $isTenant ? "tenant_{$tenantId}_users" : "central_users";
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

            $sortCol = $request->input('sort_by');
            $sortDir = $request->input('sort_direction');

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
            'engine' => $engine
        ];

        return response()->json($response);
    }

    private function applyDatabaseFilters($query, $status, $role, $dateFrom, $dateTo)
    {
        if ($status !== 'all') {
            $query->where('is_active', $status === 'active');
        }
        if ($role !== 'all') {
            $query->whereHas('roles', fn($q) => $q->where('name', $role));
        }
        if (!empty($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
    }

   public function store(Request $request)
{
    $validated = $request->validate([
        'name'        => 'required|string|max:255',
        'email'       => 'required|email|unique:users,email',
        'role'        => 'required|string|exists:roles,name',
        'avatar_path' => 'nullable|string',
        'password'    => 'nullable|string|min:8',
    ]);

    if ($validated['role'] === 'Super Admin') {
        return $this->error('Access Denied: Cannot provision Super Admin accounts via API.', 403);
    }

    $rawPassword = $request->input('password') ?: \Illuminate\Support\Str::random(16);

    $user = User::create([
        'name'        => $validated['name'],
        'email'       => $validated['email'],
        'password'    => \Illuminate\Support\Facades\Hash::make($rawPassword),
        'is_active'   => true,
        'avatar_path' => $request->input('avatar_path'),
    ]);

    $user->guard_name = $this->resolveGuard();
    $user->assignRole($validated['role']);

    \Illuminate\Support\Facades\Cache::forget("user_stats_{$user->guard_name}");

    try {
        $token = \Illuminate\Support\Facades\Password::createToken($user);
        $tenantId = function_exists('tenant') && tenant()
            ? tenant('id')
            : null;

        \Illuminate\Support\Facades\Mail::to($user->email)->queue(
            new \Modules\Identity\Mail\UserCreated(
                user: $user,
                token: $token,
                rawPassword: $rawPassword,
                tenantId: $tenantId
            )
        );
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::warning("UserCreated email failed: " . $e->getMessage());
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
            'name'          => 'sometimes|string|max:255',
            'email'         => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'role'          => 'sometimes|string|exists:roles,name',
            'avatar_path'   => 'nullable|string', // 🚀 FIX: Accept string instead of image
            'remove_avatar' => 'nullable|boolean',
            'password'      => ['nullable', 'string', PasswordRule::min(6)->mixedCase()->numbers()->symbols()]
        ]);

        // 🚀 FIX: Handle avatar path strings natively
        if ($request->input('remove_avatar')) {
            $user->avatar_path = null;
        } elseif ($request->has('avatar_path')) {
            $user->avatar_path = $request->input('avatar_path');
        }

        $userData = $request->only(['name', 'email']);

        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->input('password'));
            Log::info("Password manually updated for user ID: {$user->id}");
        }

        $user->update($userData);
        $user->save(); // Ensure any avatar_path assignments are committed

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
            Log::warning("UserUpdated email failed: " . $e->getMessage());
        }

        return $this->success($user, 'Operator record updated.');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if ($user->id === 1 || $user->id === '1') {
            return $this->error('CRITICAL: Cannot delete the Core Overlord.', 403);
        }

        if ($user->avatar_path) Storage::disk('public')->delete($user->avatar_path);

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
            Log::warning("UserStatusChanged email failed: " . $e->getMessage());
        }

        $status = $user->is_active ? 'activated' : 'deactivated';
        return $this->success($user, "Operator successfully {$status}.");
    }

    // 🚀 BULLETPROOF IMPERSONATION METHOD
    public function impersonate(Request $request, $id)
    {
        try {
            // 1. Get the authenticated ID
            $currentUserId = $request->user()->id;

            // 2. Explicitly fetch the user using the Identity Module's Model
            // This guarantees the Spatie 'hasRole' and Sanctum 'createToken' traits are available!
            $identityUser = User::find($currentUserId);

            // 3. Security Check
            if (!$identityUser || !$identityUser->hasRole('Super Admin')) {
                return $this->error('CRITICAL: Insufficient clearance. Only Super Admins can impersonate users.', 403);
            }

            // 4. Fetch target user
            $userToImpersonate = User::findOrFail($id);

            if ($userToImpersonate->id === 1 || $userToImpersonate->id === '1') {
                return $this->error('Cannot impersonate the Core Overlord.', 403);
            }

            // 5. Generate a new temporary token
            $token = $userToImpersonate->createToken('impersonation_token')->plainTextToken;

            return $this->success([
                'token' => $token,
                'user' => $userToImpersonate
            ], 'Impersonation sequence activated.');

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Impersonation Error: ' . $e->getMessage());
            return $this->error('System Crash: ' . $e->getMessage() . ' on line ' . $e->getLine(), 500);
        }
    }
}
