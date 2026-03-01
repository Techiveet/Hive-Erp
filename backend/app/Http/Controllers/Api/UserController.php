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

// 🚀 FIXED: Added the missing import for PasswordRule
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

    public function index(Request $request)
    {
        $query = User::with('roles');

        if ($request->filled('search')) {
            $tenantId = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';

            $rawIds = User::search($request->search)
                ->where('tenant_id', $tenantId)
                ->keys();
            
            if ($rawIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $dbIds = collect($rawIds)->map(fn($id) => explode('_', $id)[1] ?? $id)->toArray();
                $query->whereIn('id', $dbIds);
            }
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('role') && $request->role !== 'all') {
            $query->whereHas('roles', fn($q) => $q->where('name', $request->role));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $query->orderByRaw('id = 1 DESC');
        
        if ($request->filled('sort_by') && $request->filled('sort_direction')) {
            $query->orderBy($request->sort_by, $request->sort_direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->input('pageSize', 10);
        return response()->json($query->paginate($perPage));
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
            // 🚀 Enforcing network security rules
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

        // 🚀 ONLY update password if they typed a new one
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
                
                // Remove password hash from the email payload
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