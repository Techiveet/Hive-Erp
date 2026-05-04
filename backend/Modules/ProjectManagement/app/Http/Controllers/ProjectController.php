<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Models\ProjectComment;
use Modules\ProjectManagement\Events\ProjectManagementUpdated;
use Modules\ProjectManagement\Support\ProjectManagementNotifier;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(
                in_array($request->string('status')->toString(), ['planning', 'active', 'on_hold', 'completed', 'archived'], true),
                fn ($query) => $query->where('status', $request->string('status')->toString())
            )
            ->when($request->boolean('is_template'), fn ($query) => $query->where('is_template', true))
            ->when(!$request->has('is_template'), fn ($query) => $query->where('is_template', false))
            ->withCount([
                'tasks',
                'members',
                'tasks as completed_tasks_count' => fn ($query) => $query->whereHas('column', fn ($query) => $query->where('is_done', true)),
            ])
            ->with(['creator:id,name,avatar_path,two_factor_confirmed_at', 'projectManager:id,name,avatar_path,two_factor_confirmed_at', 'managers', 'members.user:id,name,avatar_path,two_factor_confirmed_at'])
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($projects);
    }

    public function summary()
    {
        $stats = [
            'total' => Project::count(),
            'active' => Project::where('status', 'active')->count(),
            'completed' => Project::where('status', 'completed')->count(),
            'planning' => Project::where('status', 'planning')->count(),
        ];

        $recent = Project::withCount([
                'tasks',
                'members',
                'tasks as completed_tasks_count' => fn ($query) => $query->whereHas('column', fn ($query) => $query->where('is_done', true)),
            ])
            ->with(['projectManager:id,name,avatar_path,two_factor_confirmed_at', 'managers', 'members.user:id,name,avatar_path,two_factor_confirmed_at'])
            ->latest()
            ->take(6)
            ->get();

        return response()->json([
            'stats' => $stats,
            'recent' => $recent,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|string|in:planning,active,on_hold,completed,archived',
            'priority' => 'sometimes|string|in:low,medium,high,urgent',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'project_manager_ids' => 'nullable|array',
            'project_manager_ids.*' => 'exists:users,id',
            'client_stakeholder' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'attachments' => 'nullable|array',
            'assigned_to' => 'nullable|array',
            'assigned_to.*' => 'exists:users,id',
            'is_template' => 'sometimes|boolean',
            'template_settings' => 'nullable|array',
            'budget' => 'nullable|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'hourly_rate' => 'nullable|numeric|min:0',
            'estimated_hours' => 'nullable|numeric|min:0',
            'estimated_revenue' => 'nullable|numeric|min:0',
            'repository_url' => 'nullable|url',
            'tech_stack' => 'nullable|array',
        ]);

        $project = DB::transaction(function () use ($validated) {
            $projectData = collect($validated)->except(['assigned_to', 'project_manager_ids'])->toArray();
            
            // Set the first manager as the primary manager for backward compatibility
            if (!empty($validated['project_manager_ids'])) {
                $projectData['project_manager_id'] = $validated['project_manager_ids'][0];
            }

            $project = Project::create(array_merge(
                $projectData,
                ['created_by' => auth()->id()]
            ));

            // Unified member management
            $membersData = [];
            
            // 1. Owner (Creator)
            $membersData[auth()->id()] = ['role' => 'owner'];
            
            // 2. Managers
            if (!empty($validated['project_manager_ids'])) {
                foreach ($validated['project_manager_ids'] as $id) {
                    if (!isset($membersData[$id])) {
                        $membersData[$id] = ['role' => 'manager'];
                    }
                }
            }
            
            // 3. Members
            if (!empty($validated['assigned_to'])) {
                foreach ($validated['assigned_to'] as $id) {
                    if (!isset($membersData[$id])) {
                        $membersData[$id] = ['role' => 'member'];
                    }
                }
            }
            
            $project->allMembers()->sync($membersData);

            // Create default board and columns
            $board = $project->boards()->create([
                'name' => 'Main Board',
                'order' => 0,
            ]);

            $columns = ['Backlog', 'In Progress', 'In Review', 'Done'];
            foreach ($columns as $index => $name) {
                $board->columns()->create([
                    'name' => $name,
                    'order' => $index,
                    'is_done' => $name === 'Done',
                ]);
            }

            $project->load('boards.columns', 'members.user:id,name,email,avatar_path,two_factor_confirmed_at', 'creator:id,name,avatar_path,two_factor_confirmed_at', 'projectManager:id,name,avatar_path,two_factor_confirmed_at', 'managers');

            return $project;
        });

        event(new ProjectManagementUpdated('project.created', [
            'project' => $project->toArray(),
        ], $project->id));

        // Notify all project managers
        foreach ($project->managers as $manager) {
            if ($manager->id !== auth()->id()) {
                ProjectManagementNotifier::notifyUser($manager, 'pm_project_assigned', [
                    'title' => 'Project Manager Assignment',
                    'body' => "You have been assigned as a manager for: {$project->name}",
                    'url' => "/dashboard/project-management/projects/{$project->id}",
                    'project_id' => $project->id,
                ], auth()->id());
            }
        }

        ProjectManagementNotifier::notifyProjectMembers($project, 'pm_project_created', [
            'title' => 'New Project',
            'body' => "You have been added to project: {$project->name}",
            'url' => "/dashboard/project-management/projects/{$project->id}",
            'project_id' => $project->id,
        ], auth()->id());

        return response()->json($project, 201);
    }

    public function show($id)
    {
        $project = Project::with([
            'boards.columns.tasks' => fn($query) => $query->where('is_backlog', false)
                ->with(['assignees:id,name,avatar_path,two_factor_confirmed_at', 'creator:id,name,avatar_path,two_factor_confirmed_at']),
            'members.user:id,name,email,avatar_path,two_factor_confirmed_at',
            'creator:id,name,avatar_path,two_factor_confirmed_at',
            'projectManager:id,name,avatar_path,two_factor_confirmed_at',
            'managers',
            'sprints.tasks.assignees:id,name,avatar_path,two_factor_confirmed_at',
        ])->findOrFail($id);

        return response()->json($project);
    }

    public function update(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        $oldPmId = $project->getOriginal('project_manager_id');
        $oldStatus = $project->getOriginal('status');
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|string|in:planning,active,on_hold,completed,archived',
            'priority' => 'sometimes|string|in:low,medium,high,urgent',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'project_manager_ids' => 'nullable|array',
            'project_manager_ids.*' => 'exists:users,id',
            'client_stakeholder' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'attachments' => 'nullable|array',
            'assigned_to' => 'nullable|array',
            'assigned_to.*' => 'exists:users,id',
            'is_template' => 'sometimes|boolean',
            'template_settings' => 'nullable|array',
            'budget' => 'nullable|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'hourly_rate' => 'nullable|numeric|min:0',
            'estimated_hours' => 'nullable|numeric|min:0',
            'estimated_revenue' => 'nullable|numeric|min:0',
            'repository_url' => 'nullable|url',
            'tech_stack' => 'nullable|array',
        ]);

        $projectData = collect($validated)->except(['assigned_to', 'project_manager_ids'])->toArray();

        // Set the first manager as the primary manager for backward compatibility
        if (array_key_exists('project_manager_ids', $validated)) {
            $projectData['project_manager_id'] = !empty($validated['project_manager_ids']) ? $validated['project_manager_ids'][0] : null;
        }

        $project->update($projectData);

        // Unified member management
        $membersData = [];
        
        // 1. Keep existing owners
        $existingOwners = $project->members()->where('role', 'owner')->get();
        foreach ($existingOwners as $owner) {
            $membersData[$owner->user_id] = ['role' => 'owner'];
        }
        
        // 2. Managers
        if (array_key_exists('project_manager_ids', $validated)) {
            $managerIds = $validated['project_manager_ids'] ?? [];
            foreach ($managerIds as $id) {
                if (!isset($membersData[$id])) {
                    $membersData[$id] = ['role' => 'manager'];
                }
            }
        } else {
            // Keep existing managers if not explicitly updating them
            $existingManagers = $project->members()->where('role', 'manager')->get();
            foreach ($existingManagers as $manager) {
                if (!isset($membersData[$manager->user_id])) {
                    $membersData[$manager->user_id] = ['role' => 'manager'];
                }
            }
        }
        
        // 3. Members
        if (array_key_exists('assigned_to', $validated)) {
            $assignedIds = $validated['assigned_to'] ?? [];
            foreach ($assignedIds as $id) {
                if (!isset($membersData[$id])) {
                    $membersData[$id] = ['role' => 'member'];
                }
            }
        } else {
             // Keep existing members if not explicitly updating them
            $existingMembers = $project->members()->where('role', 'member')->get();
            foreach ($existingMembers as $member) {
                if (!isset($membersData[$member->user_id])) {
                    $membersData[$member->user_id] = ['role' => 'member'];
                }
            }
        }
        
        $project->allMembers()->sync($membersData);

        $project->load('members.user:id,name,email,avatar_path,two_factor_confirmed_at', 'creator:id,name,avatar_path,two_factor_confirmed_at', 'projectManager:id,name,avatar_path,two_factor_confirmed_at', 'managers');

        event(new ProjectManagementUpdated('project.updated', [
            'project' => $project->toArray(),
        ], $project->id));

        // Notify any newly assigned managers
        if (array_key_exists('project_manager_ids', $validated)) {
            $newManagerIds = $validated['project_manager_ids'] ?? [];
            
            foreach ($project->managers as $manager) {
                if ($manager->id !== auth()->id() && $manager->id !== $oldPmId && in_array($manager->id, $newManagerIds)) {
                    ProjectManagementNotifier::notifyUser($manager, 'pm_project_assigned', [
                        'title' => 'Project Manager Assignment',
                        'body' => "You have been assigned as a manager for: {$project->name}",
                        'url' => "/dashboard/project-management/projects/{$project->id}",
                        'project_id' => $project->id,
                    ], auth()->id());
                }
            }
        }

        if ($project->status !== $oldStatus) {
            ProjectManagementNotifier::notifyProjectMembers($project, 'pm_project_status_changed', [
                'title' => 'Project Status Changed',
                'body' => "{$project->name} is now " . str_replace('_', ' ', $project->status) . '.',
                'url' => "/dashboard/project-management/projects/{$project->id}",
                'project_id' => $project->id,
                'status' => $project->status,
            ], auth()->id());
        }

        return response()->json($project);
    }

    public function destroy($id)
    {
        $project = Project::findOrFail($id);
        $payload = [
            'id' => $project->id,
            'name' => $project->name,
        ];
        $project->delete();

        event(new ProjectManagementUpdated('project.deleted', [
            'project' => $payload,
        ], $id));

        return response()->json(null, 204);
    }

    public function templates()
    {
        return response()->json(Project::where('is_template', true)->get());
    }

    public function spawn(Request $request, $id)
    {
        $template = Project::with(['boards.columns'])->findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'project_manager_ids' => 'nullable|array',
            'project_manager_ids.*' => 'exists:users,id',
            'budget' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'hourly_rate' => 'nullable|numeric|min:0',
            'estimated_hours' => 'nullable|numeric|min:0',
            'estimated_revenue' => 'nullable|numeric|min:0',
        ]);

        $project = DB::transaction(function () use ($template, $validated) {
            // Clone project
            $project = Project::create([
                'name' => $validated['name'],
                'description' => $template->description,
                'status' => 'planning',
                'priority' => $template->priority,
                'start_date' => $validated['start_date'] ?? now(),
                'project_manager_id' => !empty($validated['project_manager_ids']) ? $validated['project_manager_ids'][0] : ($template->project_manager_id ?? auth()->id()),
                'created_by' => auth()->id(),
                'budget' => array_key_exists('budget', $validated) ? $validated['budget'] : $template->budget,
                'currency' => $validated['currency'] ?? $template->currency,
                'hourly_rate' => array_key_exists('hourly_rate', $validated) ? $validated['hourly_rate'] : $template->hourly_rate,
                'estimated_hours' => array_key_exists('estimated_hours', $validated) ? $validated['estimated_hours'] : $template->estimated_hours,
                'estimated_revenue' => array_key_exists('estimated_revenue', $validated) ? $validated['estimated_revenue'] : $template->estimated_revenue,
                'is_template' => false,
            ]);

            // Add owner
            $membersData = [
                (string) auth()->id() => ['role' => 'owner']
            ];

            // Add project managers
            $managerIds = !empty($validated['project_manager_ids']) ? $validated['project_manager_ids'] : [$template->project_manager_id ?? auth()->id()];
            foreach (array_unique($managerIds) as $id) {
                if (!isset($membersData[(string) $id])) {
                    $membersData[(string) $id] = ['role' => 'manager'];
                }
            }

            // Copy members from template if any
            foreach ($template->members as $member) {
                if (!isset($membersData[(string) $member->user_id])) {
                    $membersData[(string) $member->user_id] = ['role' => $member->role];
                }
            }

            $project->allMembers()->sync($membersData);

            // Clone boards and columns
            foreach ($template->boards as $templateBoard) {
                $board = $project->boards()->create([
                    'name' => $templateBoard->name,
                    'order' => $templateBoard->order,
                ]);

                foreach ($templateBoard->columns as $templateColumn) {
                    $board->columns()->create([
                        'name' => $templateColumn->name,
                        'order' => $templateColumn->order,
                        'is_done' => $templateColumn->is_done,
                    ]);
                }
            }

            return $project->load('boards.columns', 'members.user');
        });

        return response()->json($project, 201);
    }

    public function reviewAttachment(Request $request, $id)
    {
        $attachment = \Modules\ProjectManagement\Models\TaskAttachment::findOrFail($id);
        
        // Only managers or project owners can review
        $project = $attachment->task->project;
        $isManager = $project->isManagerById(auth()->id());
        $isOwner = $project->members()->where('user_id', auth()->id())->where('role', 'owner')->exists();

        if (!$isManager && !$isOwner) {
            return response()->json(['message' => 'Unauthorized to review documents'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:approved,rejected',
            'review_note' => 'nullable|string',
        ]);

        $attachment->update([
            'status' => $validated['status'],
            'review_note' => $validated['review_note'],
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return response()->json($attachment);
    }

    // PROJECT DISCUSSION METHODS

    public function storeComment(Request $request, $projectId)
    {
        $project = Project::findOrFail($projectId);
        
        $validated = $request->validate([
            'content' => 'nullable|string', 
            'parent_id' => 'nullable|exists:pm_project_comments,id',
            'attachments' => 'nullable|array',
        ]);

        if (empty($validated['content']) && empty($validated['attachments'])) {
            return response()->json(['message' => 'Comment must contain text or attachments.'], 422);
        }

        $comment = ProjectComment::create([
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'parent_id' => $validated['parent_id'] ?? null,
            'content' => $validated['content'] ?? '',
            'attachments' => $validated['attachments'] ?? null,
        ]);

        $comment->load('user:id,name,email,avatar_path,two_factor_confirmed_at');

        event(new ProjectManagementUpdated('comment.created', [
            'comment' => $comment->toArray(),
            'project_id' => $project->id,
        ], $project->id));

        ProjectManagementNotifier::notifyProjectMembers($project, 'pm_project_comment', [
            'title' => 'New Project Comment',
            'body' => "New comment on project: {$project->name}",
            'url' => "/dashboard/project-management/projects/{$project->id}",
            'project_id' => $project->id,
            'sender_id' => auth()->id(),
        ], auth()->id());

        return response()->json($comment, 201);
    }

    public function updateComment(Request $request, $commentId)
    {
        $comment = ProjectComment::findOrFail($commentId);
        
        $validated = $request->validate([
            'content' => 'nullable|string',
            'attachments' => 'nullable|array',
        ]);

        if (empty($validated['content']) && empty($validated['attachments'])) {
            return response()->json(['message' => 'Comment must contain text or attachments.'], 422);
        }

        $comment->update([
            'content' => $validated['content'] ?? '',
            'attachments' => $validated['attachments'] ?? null,
        ]);

        $comment->load('user:id,name,email,avatar_path,two_factor_confirmed_at');

        event(new ProjectManagementUpdated('comment.updated', [
            'comment' => $comment->toArray(),
            'project_id' => $comment->project_id,
        ], $comment->project_id));

        return response()->json($comment);
    }

    public function destroyComment($commentId)
    {
        $comment = ProjectComment::findOrFail($commentId);
        $projectId = $comment->project_id;
        $payload = $comment->toArray();
        $comment->delete();

        event(new ProjectManagementUpdated('comment.deleted', [
            'comment' => $payload,
        ], $projectId));

        return response()->json(null, 204);
    }
}