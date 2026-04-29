<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\ProjectManagement\Models\Task;
use Modules\ProjectManagement\Models\Column;
use Modules\ProjectManagement\Models\TaskChecklist;
use Modules\ProjectManagement\Models\TaskComment;
use Modules\ProjectManagement\Events\ProjectManagementUpdated;
use App\Notifications\ProjectManagementNotification;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\User;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $query = Task::query()->with(['project:id,name', 'column:id,name', 'assignee:id,name,avatar_path']);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->input('assigned_to'));
        }

        if ($request->filled('status')) {
            $query->whereHas('column', function ($q) use ($request) {
                $q->where('name', $request->input('status'));
            });
        }

        return response()->json($query->latest()->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'project_id' => 'required|exists:pm_projects,id',
            'column_id' => 'required|exists:pm_columns,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'sometimes|required|string|in:low,medium,high,urgent',
            'due_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
            'order' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        $this->ensureColumnBelongsToProject($validated['column_id'], $validated['project_id']);

        $maxOrder = Task::where('column_id', $validated['column_id'])->max('order') ?? -1;
        $task = Task::create(array_merge($validated, [
            'created_by' => auth()->id(),
            'order' => $maxOrder + 1,
        ]));

        $task->load(['assignee:id,name,email,avatar_path', 'creator:id,name,avatar_path', 'column:id,name', 'project:id,name']);

        event(new ProjectManagementUpdated('task.created', [
            'task' => $task->toArray(),
        ], $task->project_id));

        if ($task->assigned_to && $task->assigned_to !== auth()->id()) {
            $task->assignee->notify(new ProjectManagementNotification('pm_task_assigned', [
                'title' => 'New Task Assigned',
                'body' => "You have been assigned to: {$task->title}",
                'url' => "/dashboard/projects?projectId={$task->project_id}&taskId={$task->id}",
                'task_id' => $task->id,
                'project_id' => $task->project_id,
            ]));
        }

        return response()->json($task, 201);
    }

    public function show($id)
    {
        $task = Task::with([
            'project:id,name',
            'column:id,name',
            'assignee:id,name,email,avatar_path',
            'creator:id,name,avatar_path',
            'checklists',
            'comments.user:id,name,avatar_path',
            'attachments.fileEntry',
            'timeLogs.user:id,name,avatar_path'
        ])->findOrFail($id);

        return response()->json($task);
    }

    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'column_id' => 'sometimes|required|exists:pm_columns,id',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'sometimes|required|string|in:low,medium,high,urgent',
            'due_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
            'order' => 'sometimes|integer',
            'attachments' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        if (isset($validated['column_id'])) {
            $this->ensureColumnBelongsToProject($validated['column_id'], $task->project_id);
        }

        $oldAssigneeId = $task->getOriginal('assigned_to');
        $task->update($validated);

        $task->load(['assignee:id,name,email,avatar_path', 'creator:id,name,avatar_path', 'column:id,name', 'project:id,name']);

        event(new ProjectManagementUpdated('task.updated', [
            'task' => $task->toArray(),
        ], $task->project_id));

        if ($task->assigned_to && $task->assigned_to !== auth()->id() && $task->assigned_to !== $oldAssigneeId) {
            $task->assignee->notify(new ProjectManagementNotification('pm_task_assigned', [
                'title' => 'Task Assigned',
                'body' => "You have been assigned to: {$task->title}",
                'url' => "/dashboard/projects?projectId={$task->project_id}&taskId={$task->id}",
                'task_id' => $task->id,
                'project_id' => $task->project_id,
            ]));
        }

        return response()->json($task);
    }

    public function move(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'column_id' => 'required|exists:pm_columns,id',
            'order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $newColumnId = $validated['column_id'];
        $newOrder = $validated['order'];
        $oldColumnId = $task->column_id;
        $oldOrder = $task->order;

        $this->ensureColumnBelongsToProject($newColumnId, $task->project_id);

        $task = DB::transaction(function () use ($task, $newColumnId, $newOrder, $oldColumnId, $oldOrder) {
            if ($oldColumnId === $newColumnId) {
                // Reordering in the same column
                if ($newOrder > $oldOrder) {
                    Task::where('column_id', $oldColumnId)
                        ->where('order', '>', $oldOrder)
                        ->where('order', '<=', $newOrder)
                        ->decrement('order');
                } elseif ($newOrder < $oldOrder) {
                    Task::where('column_id', $oldColumnId)
                        ->where('order', '>=', $newOrder)
                        ->where('order', '<', $oldOrder)
                        ->increment('order');
                }
            } else {
                // Moving to a different column
                // 1. Shift tasks in old column down
                Task::where('column_id', $oldColumnId)
                    ->where('order', '>', $oldOrder)
                    ->decrement('order');

                // 2. Shift tasks in new column up
                Task::where('column_id', $newColumnId)
                    ->where('order', '>=', $newOrder)
                    ->increment('order');
            }

            $task->update([
                'column_id' => $newColumnId,
                'order' => $newOrder,
            ]);

            return $task->fresh(['assignee:id,name,email,avatar_path', 'creator:id,name,avatar_path', 'column:id,name', 'project:id,name']);
        });

        event(new ProjectManagementUpdated('task.moved', [
            'task' => $task->toArray(),
            'from_column_id' => $oldColumnId,
            'to_column_id' => $newColumnId,
            'order' => $newOrder,
        ], $task->project_id));

        return response()->json(['success' => true, 'task' => $task]);
    }

    public function destroy($id)
    {
        $task = Task::findOrFail($id);
        $projectId = $task->project_id;
        $payload = [
            'id' => $task->id,
            'project_id' => $task->project_id,
            'column_id' => $task->column_id,
            'title' => $task->title,
        ];
        $task->delete();

        event(new ProjectManagementUpdated('task.deleted', [
            'task' => $payload,
        ], $projectId));

        return response()->json(null, 204);
    }

    public function storeChecklist(Request $request, $taskId)
    {
        $task = Task::findOrFail($taskId);
        $validated = $request->validate([
            'item' => 'required|string|max:255',
            'order' => 'integer',
        ]);

        $checklist = TaskChecklist::create(array_merge($validated, ['task_id' => $task->id]));

        event(new ProjectManagementUpdated('checklist.created', [
            'checklist' => $checklist->toArray(),
            'task_id' => $task->id,
        ], $task->project_id));

        return response()->json($checklist, 201);
    }

    public function updateChecklist(Request $request, $checklistId)
    {
        $checklist = TaskChecklist::findOrFail($checklistId);
        $checklist->update($request->only(['item', 'is_completed', 'order']));
        $checklist->load('task:id,project_id');

        event(new ProjectManagementUpdated('checklist.updated', [
            'checklist' => $checklist->toArray(),
            'task_id' => $checklist->task_id,
        ], $checklist->task?->project_id));

        return response()->json($checklist);
    }

    public function destroyChecklist($checklistId)
    {
        $checklist = TaskChecklist::with('task:id,project_id')->findOrFail($checklistId);
        $payload = $checklist->toArray();
        $projectId = $checklist->task?->project_id;
        $checklist->delete();

        event(new ProjectManagementUpdated('checklist.deleted', [
            'checklist' => $payload,
            'task_id' => $payload['task_id'] ?? null,
        ], $projectId));

        return response()->json(null, 204);
    }

    public function storeComment(Request $request, $taskId)
    {
        $task = Task::findOrFail($taskId);
        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => auth()->id(),
            'content' => $validated['content'],
        ]);

        $comment->load('user:id,name,email,avatar_path');

        event(new ProjectManagementUpdated('comment.created', [
            'comment' => $comment->toArray(),
            'task_id' => $task->id,
        ], $task->project_id));

        if ($task->assigned_to && $task->assigned_to !== auth()->id()) {
            $task->assignee->notify(new ProjectManagementNotification('pm_task_comment', [
                'title' => 'New Task Comment',
                'body' => "New comment on your task: {$task->title}",
                'url' => "/dashboard/projects?projectId={$task->project_id}&taskId={$task->id}",
                'task_id' => $task->id,
                'project_id' => $task->project_id,
                'sender_id' => auth()->id(),
            ]));
        }

        return response()->json($comment, 201);
    }

    private function ensureColumnBelongsToProject(string $columnId, string $projectId): void
    {
        $belongs = Column::query()
            ->whereKey($columnId)
            ->whereHas('board', fn ($query) => $query->where('project_id', $projectId))
            ->exists();

        abort_unless($belongs, 422, 'The selected column does not belong to this project.');
    }
}
