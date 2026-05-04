<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Models\Sprint;
use Modules\ProjectManagement\Models\Task;
use Illuminate\Support\Facades\DB;

class SprintController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'goal' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $sprint = $project->sprints()->create(array_merge($validated, [
            'status' => 'upcoming',
        ]));

        return response()->json($sprint, 201);
    }

    public function update(Request $request, Sprint $sprint)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'goal' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'sometimes|required|string|in:upcoming,active,completed',
        ]);

        $sprint->update($validated);

        return response()->json($sprint);
    }

    public function destroy(Sprint $sprint)
    {
        // Move tasks back to backlog if sprint is deleted
        Task::where('sprint_id', $sprint->id)->update([
            'sprint_id' => null,
            'is_backlog' => true,
        ]);

        $sprint->delete();

        return response()->json(null, 204);
    }

    public function start(Sprint $sprint)
    {
        // Ensure no other active sprint for this project
        Sprint::where('project_id', $sprint->project_id)
            ->where('status', 'active')
            ->update(['status' => 'completed']);

        $sprint->update(['status' => 'active']);

        // Mark tasks in sprint as not backlog
        Task::where('sprint_id', $sprint->id)->update(['is_backlog' => false]);

        return response()->json($sprint);
    }

    public function complete(Sprint $sprint)
    {
        $sprint->update(['status' => 'completed']);

        // Move incomplete tasks to backlog or next sprint?
        // For now, just mark them as backlog if they are not in "Done" column
        $doneColumnId = DB::table('pm_columns')
            ->join('pm_boards', 'pm_columns.board_id', '=', 'pm_boards.id')
            ->where('pm_boards.project_id', $sprint->project_id)
            ->where('pm_columns.is_done', true)
            ->value('pm_columns.id');

        if ($doneColumnId) {
            Task::where('sprint_id', $sprint->id)
                ->where('column_id', '!=', $doneColumnId)
                ->update([
                    'sprint_id' => null,
                    'is_backlog' => true,
                ]);
        }

        return response()->json($sprint);
    }
}
