<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\ProjectManagement\Models\Board;
use Modules\ProjectManagement\Models\Column;
use Modules\ProjectManagement\Events\ProjectManagementUpdated;

class BoardController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:pm_projects,id',
            'name' => 'required|string|max:255',
            'order' => 'integer',
        ]);

        $board = Board::create($validated);

        event(new ProjectManagementUpdated('board.created', [
            'board' => $board->toArray(),
        ], $board->project_id));

        return response()->json($board, 201);
    }

    public function update(Request $request, $id)
    {
        $board = Board::findOrFail($id);
        $board->update($request->validate([
            'name' => 'sometimes|required|string|max:255',
            'order' => 'sometimes|integer',
        ]));

        event(new ProjectManagementUpdated('board.updated', [
            'board' => $board->toArray(),
        ], $board->project_id));

        return response()->json($board);
    }

    public function destroy($id)
    {
        $board = Board::findOrFail($id);
        $projectId = $board->project_id;
        $payload = $board->toArray();
        $board->delete();

        event(new ProjectManagementUpdated('board.deleted', [
            'board' => $payload,
        ], $projectId));

        return response()->json(null, 204);
    }

    public function storeColumn(Request $request, $boardId)
    {
        $board = Board::findOrFail($boardId);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string',
            'order' => 'integer',
            'is_done' => 'boolean',
        ]);

        $column = Column::create(array_merge($validated, ['board_id' => $board->id]));
        $column->load('board:id,project_id');

        event(new ProjectManagementUpdated('column.created', [
            'column' => $column->toArray(),
        ], $column->board?->project_id));

        return response()->json($column, 201);
    }

    public function updateColumn(Request $request, $columnId)
    {
        $column = Column::findOrFail($columnId);
        $column->update($request->validate([
            'name' => 'sometimes|required|string|max:255',
            'color' => 'nullable|string',
            'order' => 'sometimes|integer',
            'is_done' => 'sometimes|boolean',
        ]));
        $column->load('board:id,project_id');

        event(new ProjectManagementUpdated('column.updated', [
            'column' => $column->toArray(),
        ], $column->board?->project_id));

        return response()->json($column);
    }

    public function destroyColumn($columnId)
    {
        $column = Column::findOrFail($columnId);
        $column->load('board:id,project_id');
        $projectId = $column->board?->project_id;
        $payload = $column->toArray();
        $column->delete();

        event(new ProjectManagementUpdated('column.deleted', [
            'column' => $payload,
        ], $projectId));

        return response()->json(null, 204);
    }
}
