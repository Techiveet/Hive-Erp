<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Events\ProjectManagementUpdated;
use App\Notifications\ProjectManagementNotification;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\User;

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
            ->withCount([
                'tasks',
                'members',
                'tasks as completed_tasks_count' => fn ($query) => $query->whereHas('column', fn ($query) => $query->where('is_done', true)),
            ])
            ->with(['creator:id,name,avatar_path', 'projectManager:id,name,avatar_path', 'members.user:id,name,avatar_path'])
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
            ->with(['projectManager:id,name,avatar_path', 'members.user:id,name,avatar_path'])
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
            'end_date' => 'nullable|date',
            'project_manager_id' => 'nullable|exists:users,id',
            'client_stakeholder' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'attachments' => 'nullable|array',
            'assigned_to' => 'nullable|array',
            'assigned_to.*' => 'exists:users,id',
        ]);

        $project = DB::transaction(function () use ($validated) {
            $project = Project::create(array_merge(
                collect($validated)->except(['assigned_to'])->toArray(),
                ['created_by' => auth()->id()]
            ));

            // Add creator as owner
            $project->members()->create([
                'user_id' => auth()->id(),
                'role' => 'owner',
            ]);

            // Add project manager if specified and not the creator
            if (!empty($validated['project_manager_id']) && $validated['project_manager_id'] != auth()->id()) {
                $project->members()->updateOrCreate(
                    ['user_id' => $validated['project_manager_id']],
                    ['role' => 'manager']
                );
            }

            // Add assigned members
            if (!empty($validated['assigned_to'])) {
                foreach ($validated['assigned_to'] as $userId) {
                    $project->members()->updateOrCreate(
                        ['user_id' => $userId],
                        ['role' => 'member']
                    );
                }
            }

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

            $project->load('boards.columns', 'members.user:id,name,email,avatar_path', 'creator:id,name,avatar_path', 'projectManager:id,name,avatar_path');

            return $project;
        });

        event(new ProjectManagementUpdated('project.created', [
            'project' => $project->toArray(),
        ], $project->id));

        if ($project->project_manager_id && $project->project_manager_id !== auth()->id()) {
            $project->projectManager->notify(new ProjectManagementNotification('pm_project_assigned', [
                'title' => 'Project Manager Assignment',
                'body' => "You have been assigned as the manager for: {$project->name}",
                'url' => "/dashboard/projects?projectId={$project->id}",
                'project_id' => $project->id,
            ]));
        }

        return response()->json($project, 201);
    }

    public function show($id)
    {
        $project = Project::with([
            'boards.columns.tasks.assignee',
            'boards.columns.tasks.creator:id,name,avatar_path',
            'members.user:id,name,email,avatar_path',
            'creator:id,name,avatar_path',
            'projectManager:id,name,avatar_path',
        ])->findOrFail($id);

        return response()->json($project);
    }

    public function update(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        $oldPmId = $project->getOriginal('project_manager_id');
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|string|in:planning,active,on_hold,completed,archived',
            'priority' => 'sometimes|string|in:low,medium,high,urgent',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'project_manager_id' => 'nullable|exists:users,id',
            'client_stakeholder' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'attachments' => 'nullable|array',
        ]);
        $project->update($validated);

        $project->load('members.user:id,name,email,avatar_path', 'creator:id,name,avatar_path', 'projectManager:id,name,avatar_path');

        event(new ProjectManagementUpdated('project.updated', [
            'project' => $project->toArray(),
        ], $project->id));

        if ($project->project_manager_id && $project->project_manager_id !== auth()->id() && $project->project_manager_id !== $oldPmId) {
            $project->projectManager->notify(new ProjectManagementNotification('pm_project_assigned', [
                'title' => 'Project Manager Assignment',
                'body' => "You have been assigned as the manager for: {$project->name}",
                'url' => "/dashboard/projects?projectId={$project->id}",
                'project_id' => $project->id,
            ]));
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
}
