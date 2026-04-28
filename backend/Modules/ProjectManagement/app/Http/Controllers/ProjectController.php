<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Events\ProjectManagementUpdated;
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
            ->withCount(['tasks', 'members'])
            ->with(['creator:id,name,avatar_path', 'members.user:id,name,avatar_path'])
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

        $recent = Project::withCount(['tasks', 'members'])
            ->with(['members.user:id,name,avatar_path'])
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
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $project = DB::transaction(function () use ($validated) {
            $project = Project::create(array_merge($validated, [
                'created_by' => auth()->id(),
            ]));

            // Add creator as owner
            $project->members()->create([
                'user_id' => auth()->id(),
                'role' => 'owner',
            ]);

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

            $project->load('boards.columns', 'members.user:id,name,email,avatar_path', 'creator:id,name,avatar_path');

            return $project;
        });

        event(new ProjectManagementUpdated('project.created', [
            'project' => $project->toArray(),
        ], $project->id));

        return response()->json($project, 201);
    }

    public function show($id)
    {
        $project = Project::with([
            'boards.columns.tasks.assignee',
            'boards.columns.tasks.creator:id,name,avatar_path',
            'members.user:id,name,email,avatar_path',
            'creator:id,name,avatar_path'
        ])->findOrFail($id);

        return response()->json($project);
    }

    public function update(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        $project->update($request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|string|in:planning,active,on_hold,completed,archived',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]));

        $project->load('members.user:id,name,email,avatar_path', 'creator:id,name,avatar_path');

        event(new ProjectManagementUpdated('project.updated', [
            'project' => $project->toArray(),
        ], $project->id));

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
