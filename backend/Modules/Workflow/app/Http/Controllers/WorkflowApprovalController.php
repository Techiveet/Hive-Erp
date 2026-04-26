<?php

namespace Modules\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Workflow\Models\WorkflowApproval;
use Modules\Workflow\Models\WorkflowDefinition;
use Modules\Workflow\Models\ApprovalRole;
use Illuminate\Http\JsonResponse;

use Modules\Workflow\Services\WorkflowService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

class WorkflowApprovalController extends Controller
{
    public function __construct(
        protected WorkflowService $workflowService
    ) {}
    /**
     * Display a listing of approvals for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->get('type', 'inbox'); // inbox or requested
        $status = $request->get('status', 'pending');
        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $roleIds = $user->approvalRoles()->pluck('approval_roles.id');

        \Log::info('Fetching approvals', [
            'user_id' => $user->id,
            'type' => $type,
            'status' => $status,
            'role_ids' => $roleIds->toArray()
        ]);

        $approvals = WorkflowApproval::query()
            ->with(['approvable', 'user', 'role', 'requester']);

        if ($type === 'requested') {
            $approvals->where('requested_by', $user->id);
        } else {
            $approvals->where(function($q) use ($user, $roleIds) {
                $q->where('user_id', $user->id)
                  ->orWhereIn('role_id', $roleIds);
            });
        }
        
        if ($status && $status !== 'all') {
            $approvals->where('status', $status);
            
            // For pending items in inbox, only show if they are "up next" in the sequence
            if ($status === 'pending' && $type === 'inbox') {
                $approvals->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('workflow_approvals as prev')
                        ->whereRaw('prev.approvable_id = workflow_approvals.approvable_id')
                        ->whereRaw('prev.approvable_type = workflow_approvals.approvable_type')
                        ->whereRaw('prev.sequence < workflow_approvals.sequence')
                        ->where('prev.status', '!=', 'approved');
                });
            }
        }
        
        $result = $approvals->latest()->paginate($request->get('per_page', 15));
        
        \Log::info('Approvals found', [
            'count' => $result->total()
        ]);

        return response()->json($result);
    }

    /**
     * Action an approval (approve/reject).
     */
    public function update(Request $request, WorkflowApproval $approval): JsonResponse
    {
        $user = auth()->user();
        $isAuthorized = $approval->user_id === $user->id || 
                        ($approval->role_id && $user->approvalRoles()->where('approval_roles.id', $approval->role_id)->exists());

        if (!$isAuthorized) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'notes' => 'nullable|string',
        ]);

        // If it was a role assignment, lock it to the user who actioned it
        if ($approval->role_id && !$approval->user_id) {
            $approval->user_id = $user->id;
            $approval->save();
        }

        $this->workflowService->actionApproval(
            $approval,
            $validated['status'],
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Approval updated successfully',
            'approval' => $approval->fresh(['approvable', 'user', 'role']),
        ]);
    }

    /**
     * Assign approvers to a model.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            \Log::info('Storing approvals', [
                'user_id' => $user?->id,
                'request_data' => $request->all()
            ]);

            if (!$user) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            $validated = $request->validate([
                'approvable_type' => 'required|string',
                'approvable_id' => 'required|integer',
                'approvers' => 'required|array',
                'approvers.*.user_id' => 'nullable|integer',
                'approvers.*.role_id' => 'nullable|integer',
                'approvers.*.sequence' => 'nullable|integer',
                'approvers.*.department' => 'nullable|string',
            ]);

            $modelClass = $validated['approvable_type'];
            $modelId = $validated['approvable_id'];

            if (!class_exists($modelClass)) {
                return response()->json(['error' => "Model class {$modelClass} not found"], 422);
            }

            $created = DB::transaction(function () use ($validated, $modelClass, $modelId, $user) {
                $records = [];
                foreach ($validated['approvers'] as $data) {
                    $records[] = WorkflowApproval::create([
                        'approvable_type' => $modelClass,
                        'approvable_id' => $modelId,
                        'user_id' => $data['user_id'] ?? null,
                        'role_id' => $data['role_id'] ?? null,
                        'sequence' => $data['sequence'] ?? 1,
                        'department' => $data['department'] ?? null,
                        'status' => 'pending',
                        'requested_by' => $user->id,
                        'assigned_at' => now(),
                    ]);
                }
                return $records;
            });

            \Log::info('Approvals created', [
                'count' => count($created)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Approvers assigned successfully',
                'count' => count($created),
                'approvals' => $created,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed for approvals', [
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', \Illuminate\Support\Arr::flatten($e->errors())),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Failed to store approvals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign approvers: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all workflow definitions.
     */
    public function getDefinitions(): JsonResponse
    {
        return response()->json(WorkflowDefinition::latest()->get());
    }

    /**
     * Store a workflow definition.
     */
    public function storeDefinition(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'model_type' => 'required|string',
            'approver_ids' => 'required|array',
            'approver_ids.*' => 'exists:users,id',
            'required_approvals' => 'required|integer|min:1',
            'trigger_event' => 'required|string',
        ]);

        $definition = WorkflowDefinition::create($validated);

        return response()->json($definition);
    }

    /**
     * Delete a workflow definition.
     */
    public function destroyDefinition(WorkflowDefinition $definition): JsonResponse
    {
        $definition->delete();
        return response()->json(['message' => 'Definition deleted']);
    }

    public function getApprovalRoles(Request $request): JsonResponse
    {
        $query = ApprovalRole::with('users:id,name,email,avatar_path');

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->get('status') !== 'all') {
            $query->where('is_active', $request->get('status') === 'active');
        }

        if ($request->has('sort_by')) {
            $sortBy = $request->get('sort_by');
            $sortDir = $request->get('sort_direction', 'asc');
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('name', 'asc');
        }

        $roles = $query->paginate($request->get('per_page', 10));

        return response()->json($roles);
    }

    public function storeApprovalRole(Request $request): JsonResponse
    {
        if (!auth()->user()->hasAdministrativeRole()) {
            return response()->json(['message' => 'Only administrators can manage approval roles'], 403);
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|unique:approval_roles,name',
                'description' => 'nullable|string',
                'permissions' => 'nullable|array',
                'user_ids' => 'nullable|array',
                'user_ids.*' => 'integer',
            ]);

            $role = ApprovalRole::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'permissions' => $validated['permissions'] ?? null,
                'is_active' => true,
            ]);

            if (!empty($validated['user_ids'])) {
                $role->users()->sync(array_filter($validated['user_ids']));
            }

            $role->load('users:id,name,email,avatar_path');

            return response()->json($role, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $firstError = collect($errors)->flatten()->first();
            return response()->json([
                'message' => $firstError ?: 'Validation failed',
                'errors' => $errors,
            ], 422);
        } catch (\Exception $e) {
            \Log::error('ApprovalRole creation failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to create approval role: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateApprovalRole(Request $request, ApprovalRole $approvalRole): JsonResponse
    {
        if (!auth()->user()->hasAdministrativeRole()) {
            return response()->json(['message' => 'Only administrators can manage approval roles'], 403);
        }

        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|unique:approval_roles,name,' . $approvalRole->id,
                'description' => 'nullable|string',
                'permissions' => 'nullable|array',
                'user_ids' => 'nullable|array',
                'user_ids.*' => 'integer',
                'is_active' => 'nullable|boolean',
            ]);

            $updateData = array_filter([
                'name' => $validated['name'] ?? null,
                'description' => $validated['description'] ?? null,
                'permissions' => $validated['permissions'] ?? null,
                'is_active' => $validated['is_active'] ?? null,
            ], fn($v) => $v !== null);

            if (!empty($updateData)) {
                $approvalRole->update($updateData);
            }

            if (array_key_exists('user_ids', $validated) && is_array($validated['user_ids'])) {
                $cleanedIds = array_map('intval', array_filter($validated['user_ids']));
                $approvalRole->users()->sync($cleanedIds);
            }

            $approvalRole->load('users:id,name,email,avatar_path');

            return response()->json($approvalRole);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $firstError = collect($errors)->flatten()->first();
            \Log::error('ApprovalRole validation failed', ['errors' => $errors]);
            return response()->json([
                'message' => $firstError ?: 'Validation failed',
                'errors' => $errors,
            ], 422);
        } catch (\Exception $e) {
            \Log::error('ApprovalRole update failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return response()->json([
                'message' => 'Failed to update: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroyApprovalRole(ApprovalRole $approvalRole): JsonResponse
    {
        if (!auth()->user()->hasAdministrativeRole()) {
            return response()->json(['message' => 'Only administrators can manage approval roles'], 403);
        }

        $approvalRole->delete();
        return response()->json(['message' => 'Approval role deleted']);
    }

    public function getAvailableUsers(): JsonResponse
    {
        $users = User::where('is_active', true)->get(['id', 'name', 'email', 'avatar_path']);
        return response()->json($users);
    }

    public function syncUsers(Request $request, ApprovalRole $approvalRole): JsonResponse
    {
        if (!auth()->user()->hasAdministrativeRole()) {
            return response()->json(['message' => 'Only administrators can manage approval roles'], 403);
        }

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $approvalRole->users()->sync($validated['user_ids']);
        
        $approvalRole->load('users:id,name,email,avatar_path');

        return response()->json([
            'message' => 'Users synced successfully',
            'role' => $approvalRole
        ]);
    }
}
