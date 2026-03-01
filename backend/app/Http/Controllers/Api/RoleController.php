<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;

class RoleController extends Controller
{
    private function getContext(): array
    {
        $isTenant = function_exists('tenancy') && tenancy()->initialized;

        return [
            'guard' => $isTenant ? 'tenant' : 'web',
            'domain' => $isTenant ? tenant('id') . '.localhost' : 'Central (localhost)',
            'is_tenant' => $isTenant
        ];
    }

    public function index(Request $request)
    {
        $context = $this->getContext();
        $search = $request->input('search', '');
        $pageSize = $request->input('pageSize', 10);
        $tenantId = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';

        $query = Role::where('guard_name', $context['guard'])->with('permissions');

        if (!empty($search)) {
            $rawIds = Role::search($search)
                ->where('tenant_id', $tenantId)
                ->where('guard_name', $context['guard'])
                ->keys();
            
            if ($rawIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $dbIds = collect($rawIds)->map(fn($id) => explode('_', $id)[1] ?? $id)->toArray();
                $query->whereIn('id', $dbIds);
            }
        }

        $query->orderBy('created_at', 'desc');

        if ($request->has('nopaginate')) {
            return response()->json(['data' => $query->get()]);
        }

        $roles = $query->paginate($pageSize);

        return response()->json([
            'data' => $roles->items(),
            'meta' => [
                'total' => $roles->total(),
                'context' => $context['domain'],
                'guard' => $context['guard']
            ]
        ]);
    }

    public function permissions()
    {
        $context = $this->getContext();
        $cacheKey = "permissions_list_" . $context['guard'];

        $permissions = Cache::remember($cacheKey, 86400, function () use ($context) {
            return Permission::where('guard_name', $context['guard'])
                ->orderBy('name')
                ->get();
        });

        return response()->json(['data' => $permissions]);
    }

    public function store(Request $request)
    {
        $context = $this->getContext();
        $guard = $context['guard'];

        $request->validate([
            'name' => ['required', 'string', Rule::unique('roles', 'name')->where('guard_name', $guard)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', $guard)],
        ]);

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => $guard
        ]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'message' => "Role created successfully",
            'data' => $role->load('permissions')
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $context = $this->getContext();
        $guard = $context['guard'];

        $role = Role::where('id', $id)->where('guard_name', $guard)->firstOrFail();

        // 🚀 CRITICAL SECURITY LOCK: Super Admin is completely immutable
        if ($role->name === 'Super Admin') {
            return response()->json(['message' => 'The Super Admin clearance level is hardcoded and cannot be modified.'], 403);
        }

        $request->validate([
            'name' => ['required', 'string', Rule::unique('roles', 'name')->where('guard_name', $guard)->ignore($role->id)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', $guard)],
        ]);

        // Secondary Lock: Prevent renaming of the regular 'Admin' role
        if ($role->name === 'Admin' && $request->name !== $role->name) {
            return response()->json(['message' => 'Core system roles cannot be renamed.'], 403);
        }

        $role->update(['name' => $request->name]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'message' => 'Role updated successfully',
            'data' => $role->load('permissions')
        ]);
    }

    public function destroy(string $id)
    {
        $context = $this->getContext();
        $role = Role::where('id', $id)->where('guard_name', $context['guard'])->firstOrFail();

        if (in_array($role->name, ['Admin', 'Super Admin'])) {
            return response()->json(['message' => 'Core system roles cannot be deleted.'], 403);
        }

        $role->delete();
        return response()->json(['message' => 'Role deleted successfully']);
    }
}