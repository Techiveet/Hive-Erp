<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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

use App\Mail\UserCreated;
use App\Mail\UserUpdated;
use App\Mail\UserStatusChanged;

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

    /**
     * 🚀 SMART SEARCH ROUTER
     */
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
            // 🚀 ROUTE 1: MEILISEARCH ENGINE
            $indexName = $isTenant ? "tenant_{$tenantId}_users" : "central_users";

            $scout = User::search($search)->within($indexName);

            // Apply strict Database filters AFTER Meilisearch grabs the relevant IDs
            $scout->query(function ($query) use ($status, $role, $dateFrom, $dateTo) {
                $query->with('roles');
                $this->applyDatabaseFilters($query, $status, $role, $dateFrom, $dateTo);
            });

            // Let Meilisearch sort by relevance automatically!
            $users = $scout->paginate($request->input('pageSize', 10));
            $engine = 'meilisearch';
        } else {
            // 🚀 ROUTE 2: DATABASE ENGINE
            $query = User::with('roles');
            $this->applyDatabaseFilters($query, $status, $role, $dateFrom, $dateTo);

            $query->orderByRaw('id = 1 DESC'); // Always keep Core Overlord on top

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

        // Return standard Laravel Pagination format with our custom Engine Metadata
        $response = $users->toArray();
        $response['meta'] = [
            'engine' => $engine
        ];

        return response()->json($response);
    }

    /**
     * Shared filter application for dates, roles, and status
     */
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
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'role'     => 'required|string|exists:roles,name',
            'avatar'   => 'nullable|image|max:2048',
            'password' => 'nullable|string|min:8'
        ]);

        if ($validated['role'] === 'Super Admin') {
            return $this->error('Access Denied: Cannot provision Super Admin accounts via API.', 403);
        }

        $avatarPath = $request->hasFile('avatar')
            ? $request->file('avatar')->store('avatars', 'public')
            : null;

        $rawPassword = $request->input('password') ?: Str::random(16);

        $user = User::create([
            'name'        => $validated['name'],
            'email'       => $validated['email'],
            'password'    => Hash::make($rawPassword),
            'is_active'   => true,
            'avatar_path' => $avatarPath,
        ]);

        $user->guard_name = $this->resolveGuard();
        $user->assignRole($validated['role']);

        Cache::forget("user_stats_{$user->guard_name}");

        try {
            $token = Password::createToken($user);
            Mail::to($user->email)->queue(new UserCreated($user, $token, $rawPassword));
        } catch (\Exception $e) {
            Log::warning("UserCreated email failed: " . $e->getMessage());
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
            'avatar'        => 'nullable|image|max:2048',
            'remove_avatar' => 'nullable|boolean',
            'password'      => ['nullable', 'string', PasswordRule::min(6)->mixedCase()->numbers()->symbols()]
        ]);

        if ($request->input('remove_avatar') && $user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = null;
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        $userData = $request->only(['name', 'email']);

        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->input('password'));
            Log::info("Password manually updated for user ID: {$user->id}");
        }

        $user->update($userData);

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
}
