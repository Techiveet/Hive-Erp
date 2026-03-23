<?php

namespace Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        $isTenant = function_exists('tenant') && tenant('id');
        $tenantId = $isTenant ? tenant('id') : 'central';

        $query = Role::where('guard_name', $context['guard'])->with('permissions');
        $engine = 'database';

        if (!empty($search)) {
            $scoutDriver = config('scout.driver');
            $meilisearchSuccess = false;

            // 🚀 ROUTE 1: MEILISEARCH ENGINE (With Timeout Protection)
            if ($scoutDriver === 'meilisearch') {
                try {
                    $indexName = $isTenant ? "tenant_{$tenantId}_roles" : "central_roles";
                    $scout = Role::search($search)->within($indexName);

                    $scout->query(function ($q) use ($context) {
                        $q->where('guard_name', $context['guard'])->with('permissions');
                    });

                    if ($request->has('nopaginate')) {
                        $roles = $scout->get();
                    } else {
                        $roles = $scout->paginate($pageSize);
                    }

                    $engine = 'meilisearch';
                    $meilisearchSuccess = true;
                } catch (\Exception $e) {
                    Log::warning("Meilisearch failed, falling back to Database Engine: " . $e->getMessage());
                    $meilisearchSuccess = false;
                }
            }

            // 🚀 ROUTE 2: DATABASE ENGINE (Supports searching Permission Names)
            if (!$meilisearchSuccess) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhereHas('permissions', function ($pq) use ($search) {
                          $pq->where('name', 'like', "%{$search}%");
                      });
                });

                $query->orderBy('created_at', 'desc');

                if ($request->has('nopaginate')) {
                    $roles = $query->get();
                } else {
                    $roles = $query->paginate($pageSize);
                }

                $engine = $scoutDriver === 'meilisearch' ? 'database_fallback' : 'database';
            }
        } else {
            $query->orderBy('created_at', 'desc');

            if ($request->has('nopaginate')) {
                $roles = $query->get();
            } else {
                $roles = $query->paginate($pageSize);
            }
        }

        $data = $request->has('nopaginate') ? $roles : $roles->items();
        $total = $request->has('nopaginate') ? count($roles) : $roles->total();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total'   => $total,
                'context' => $context['domain'],
                'guard'   => $context['guard'],
                'engine'  => $engine
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

        // 🚀 THE FIX: Broadcast that a Role was created so React counts UP (+1)
        activity('Security Management')
            ->causedBy(auth()->user())
            ->performedOn($role)
            ->event('created')
            ->log("Provisioned new security role [{$role->name}].");

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

        if ($role->name === 'Super Admin') {
            return response()->json(['message' => 'The Super Admin clearance level is hardcoded and cannot be modified.'], 403);
        }

        $request->validate([
            'name' => ['required', 'string', Rule::unique('roles', 'name')->where('guard_name', $guard)->ignore($role->id)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', $guard)],
        ]);

        if ($role->name === 'Admin' && $request->name !== $role->name) {
            return response()->json(['message' => 'Core system roles cannot be renamed.'], 403);
        }

        $role->update(['name' => $request->name]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        // 🚀 THE FIX: Broadcast that a Role was updated (doesn't change count, but shows in live audit log)
        activity('Security Management')
            ->causedBy(auth()->user())
            ->performedOn($role)
            ->event('updated')
            ->log("Reconfigured security role [{$role->name}].");

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

        $roleName = $role->name; // Save name before deleting
        $role->delete();

        // 🚀 THE FIX: Broadcast that a Role was deleted so React counts DOWN (-1)
        activity('Security Management')
            ->causedBy(auth()->user())
            // Note: We don't use ->performedOn() here because the model no longer exists!
            ->event('deleted')
            ->log("Purged security role [{$roleName}].");

        return response()->json(['message' => 'Role deleted successfully']);
    }
}
