<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\ProjectManagement\Models\ProjectAutomation;
use Modules\ProjectManagement\Models\Project;

class ProjectAutomationController extends Controller
{
    public function index(Project $project)
    {
        return response()->json($project->automations()->get());
    }

    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'trigger' => 'required|string',
            'conditions' => 'nullable|array',
            'action' => 'required|string',
            'action_data' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $automation = $project->automations()->create($validated);

        return response()->json($automation, 201);
    }

    public function update(Request $request, ProjectAutomation $automation)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'trigger' => 'sometimes|string',
            'conditions' => 'nullable|array',
            'action' => 'sometimes|string',
            'action_data' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $automation->update($validated);

        return response()->json($automation);
    }

    public function destroy(ProjectAutomation $automation)
    {
        $automation->delete();
        return response()->json(null, 204);
    }
}
