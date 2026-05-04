<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Models\ProjectGoal;

class ProjectGoalController extends Controller
{
    public function index(Request $request, $project)
    {
        \Log::info("ProjectGoalController@index - project: " . $project);
        return ProjectGoal::where('project_id', $project)
            ->orderBy('order')
            ->get();
    }

    public function store(Request $request, $project)
    {
        \Log::info("ProjectGoalController@store - project: " . $project, $request->all());
        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $maxOrder = ProjectGoal::where('project_id', $project)->max('order') ?? 0;

        return ProjectGoal::create([
            'project_id' => $project,
            'title' => $validated['title'],
            'order' => $maxOrder + 1,
        ]);
    }

    public function update(Request $request, $goalId)
    {
        $goal = ProjectGoal::findOrFail($goalId);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'is_completed' => 'sometimes|boolean',
            'order' => 'sometimes|integer',
        ]);

        $goal->update($validated);

        return $goal;
    }

    public function destroy($goalId)
    {
        $goal = ProjectGoal::findOrFail($goalId);
        $goal->delete();

        return response()->noContent();
    }
}
