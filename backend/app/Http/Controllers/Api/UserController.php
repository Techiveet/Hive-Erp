<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

// Mails are queued by default to prevent Octane worker blocking
use App\Mail\UserCreated;
use App\Mail\UserUpdated;
use App\Mail\UserStatusChanged;

class UserController extends SpeedController
{
    /**
     * Helper: Detect context safely for Octane
     */
    private function resolveGuard(): string
    {
        return (function_exists('tenancy') && tenancy()->initialized) ? 'tenant' : 'web';
    }

    /**
     * 1. LIST USERS (Powered by Meilisearch & Octane-Safe Cache)
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $perPage = $request->input('pageSize', 10);
        $guard = $this->resolveGuard();

        // High-speed Redis read using tags for instant invalidation
        $stats = Cache::tags(['users', $guard])->remember("user_stats_{$guard}", 300, function () {
            return [
                'total_users'   => User::count(),
                'active_users'  => User::active()->count(),
                'new_this_week' => User::where('created_at', '>=', now()->subWeek())->count(),
            ];
        });

        // Use Meilisearch (Scout) for fast indexing and searching
        if ($search) {
            $users = User::search($search)
                ->when($request->filled('status'), function ($query) use ($request) {
                    return $query->where('is_active', $request->status === 'active');
                })
                ->paginate($perPage);
        } else {
            $users = User::with('roles')
                ->filter($request) // Uses Model-level filtering scope
                ->orderByRaw('id = 1 DESC') // Sticky Overlord (Always first)
                ->orderBy($request->input('sort_by', 'created_at'), $request->input('sort_direction', 'desc'))
                ->paginate($perPage);
        }

        return $this->success([
            'users' => $users->items(),
            'pagination' => [
                'total' => $users->total(),
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
            ],
            'stats' => $stats
        ]);
    }

    /**
     * 2. CREATE USER (Optimized Background Tasks)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'   => 'required|string|max:255',
            'email'  => 'required|email|unique:users,email',
            'role'   => 'required|string|exists:roles,name',
            'avatar' => 'nullable|image|max:2048',
        ]);

        if ($validated['role'] === 'Super Admin') {
            return $this->error('Access Denied: Tenant nodes cannot create Super Admins.', 403);
        }

        $avatarPath = $request->hasFile('avatar')
            ? $request->file('avatar')->store('avatars', 'public')
            : null;

        $user = User::create([
            'name'        => $validated['name'],
            'email'       => $validated['email'],
            'password'    => Hash::make(Str::random(32)),
            'is_active'   => true,
            'avatar_path' => $avatarPath,
        ]);

        $user->guard_name = $this->resolveGuard();
        $user->assignRole($validated['role']);

        Cache::tags(['users', $this->resolveGuard()])->flush();

        // Generate activation token and queue welcome email
        $token = Password::createToken($user);
        Mail::to($user->email)->queue(new UserCreated($user, $token));

        return $this->success($user, 'Node initialized successfully.', 201);
    }

    /**
     * 3. SHOW USER (Fixes the "undefined method" error)
     */
    public function show($id)
    {
        $user = User::with('roles')->findOrFail($id);
        return $this->success($user);
    }

    /**
     * 4. UPDATE USER
     */
    public function update(Request $request, User $user)
    {
        if ($user->id === 1) {
            return $this->error('The Core Overlord cannot be modified.', 403);
        }

        $request->validate([
            'name'  => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'role'  => 'sometimes|string|exists:roles,name',
            'avatar' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        $user->fill($request->only(['name', 'email']));
        $user->save();

        if ($request->has('role')) {
            $user->guard_name = $this->resolveGuard();
            $user->syncRoles([$request->role]);
        }

        Cache::tags(['users', $this->resolveGuard()])->flush();

        if ($user->wasChanged()) {
            Mail::to($user->email)->queue(new UserUpdated($user, $user->getChanges()));
        }

        return $this->success($user, 'User record updated.');
    }

    /**
     * 5. DELETE USER
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if ($user->id === 1) {
            return $this->error('CRITICAL: Cannot delete the Core Overlord.', 403);
        }

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $guard = $this->resolveGuard();
        $user->delete();

        Cache::tags(['users', $guard])->flush();

        return $this->success(null, 'User purged from the network.');
    }

    /**
     * 6. TOGGLE STATUS
     */
    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);

        if ($user->id === 1) {
            return $this->error('The Core Overlord cannot be deactivated.', 403);
        }

        $user->update(['is_active' => !$user->is_active]);

        Cache::tags(['users', $this->resolveGuard()])->flush();
        Mail::to($user->email)->queue(new UserStatusChanged($user));

        $status = $user->is_active ? 'activated' : 'deactivated';
        return $this->success($user, "User successfully {$status}.");
    }
}
