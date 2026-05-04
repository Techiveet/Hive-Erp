<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\ProjectManagement\Models\Task;
use Modules\ProjectManagement\Models\Column;
use Modules\ProjectManagement\Models\TaskChecklist;
use Modules\ProjectManagement\Models\TaskComment;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Events\ProjectManagementUpdated;
use Modules\ProjectManagement\Support\ProjectManagementNotifier;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $query = Task::query()->with(['project:id,name,project_manager_id,created_by', 'column:id,name,is_done', 'assignees:id,name,email,avatar_path,two_factor_confirmed_at', 'creator:id,name,avatar_path,two_factor_confirmed_at']);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        if ($request->filled('assigned_to')) {
            $query->whereHas('assignees', function($q) use ($request) {
                $q->where('users.id', $request->input('assigned_to'));
            });
        }

        if ($request->filled('status')) {
            $query->whereHas('column', function ($q) use ($request) {
                $q->where('name', $request->input('status'));
            });
        }

        return response()->json($query->latest()->paginate($request->integer('per_page', 20)));
    }

    /**
     * Get tasks assigned to the authenticated user across all projects.
     */
    public function myTasks(Request $request)
    {
        $userId = auth()->id();
        
        $query = Task::query()
            ->with([
                'project:id,name,project_manager_id,created_by', 
                'column:id,name,is_done', 
                'assignees:id,name,email,avatar_path,two_factor_confirmed_at', 
                'creator:id,name,avatar_path,two_factor_confirmed_at'
            ])
            ->whereHas('assignees', function($q) use ($userId) {
                $q->where('users.id', $userId);
            });

        if ($request->filled('status')) {
            if ($request->input('status') === 'completed') {
                $query->whereHas('column', fn($q) => $q->where('is_done', true));
            } else {
                $query->whereHas('column', fn($q) => $q->where('is_done', false));
            }
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        return response()->json($query->latest('due_date')->paginate($request->integer('per_page', 50)));
    }

    public function store(Request $request)
    {
        // Sanitize "none" values from frontend selects
        $input = $request->all();
        if (isset($input['parent_task_id']) && $input['parent_task_id'] === 'none') {
            $input['parent_task_id'] = null;
        }
        if (isset($input['environment']) && $input['environment'] === 'none') {
            $input['environment'] = null;
        }

        $validator = \Illuminate\Support\Facades\Validator::make($input, [
            'project_id' => 'required|exists:pm_projects,id',
            'column_id' => 'required|exists:pm_columns,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|string|in:low,medium,high,urgent',
            'due_date' => 'required|date',
            'assignees' => 'required|array|min:1',
            'assignees.*' => 'exists:users,id',
            'order' => 'nullable|integer',
            'progress' => 'sometimes|integer|min:0|max:100',
            'effort' => 'sometimes|nullable|string',
            'tags' => 'sometimes|nullable|array',
            'parent_task_id' => 'nullable|exists:pm_tasks,id',
            'issue_type' => 'nullable|string|in:bug,feature,task,improvement,epic,refactor,debt',
            'story_points' => 'nullable|integer|min:0',
            'environment' => 'nullable|string|in:development,staging,production,qa',
            'pr_url' => 'nullable|url',
            'is_backlog' => 'sometimes|nullable|boolean',
            'sprint_id' => 'sometimes|nullable|exists:pm_sprints,id',
        ]);

        if ($validator->fails()) {
            \Illuminate\Support\Facades\Log::info('Task creation validation failed', [
                'errors' => $validator->errors()->toArray(),
                'input' => $input
            ]);
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $project = Project::findOrFail($validated['project_id']);

        if (!empty($validated['due_date'])) {
            $dueDate = \Carbon\Carbon::parse($validated['due_date']);
            if ($project->start_date && $dueDate->lt($project->start_date)) {
                return response()->json(['errors' => ['due_date' => ['Due date cannot be before project start date (' . $project->start_date->toDateString() . ')']]], 422);
            }
            if ($project->end_date && $dueDate->gt($project->end_date)) {
                return response()->json(['errors' => ['due_date' => ['Due date cannot be after project end date (' . $project->end_date->toDateString() . ')']]], 422);
            }
        }

        if (!empty($validated['assignees'])) {
            $projectMemberIds = $project->members()->pluck('user_id')->toArray();
            foreach ($validated['assignees'] as $userId) {
                if (!in_array($userId, $projectMemberIds)) {
                    return response()->json(['errors' => ['assignees' => ['One or more selected users are not members of this project.']]], 422);
                }
            }
        }

        $this->ensureColumnBelongsToProject($validated['column_id'], $validated['project_id']);

        $task = DB::transaction(function () use ($validated) {
            $maxOrder = Task::where('column_id', $validated['column_id'])->max('order') ?? -1;
            $order = isset($validated['order']) ? $validated['order'] : $maxOrder + 1;

            $task = Task::create(array_merge(collect($validated)->except(['assignees', 'order'])->toArray(), [
                'created_by' => auth()->id(),
                'order' => $order,
                'is_backlog' => $validated['is_backlog'] ?? false, // Default to board (not backlog) if not specified
                'sprint_id' => $validated['sprint_id'] ?? null,
            ]));

            if (!empty($validated['assignees'])) {
                $task->assignees()->sync($validated['assignees']);
            }

            return $task;
        });

        $task->load(['assignees:id,name,email,avatar_path,two_factor_confirmed_at', 'creator:id,name,email,avatar_path,two_factor_confirmed_at', 'column:id,name,is_done', 'project:id,name,project_manager_id,created_by']);

        $broadcastPayload = collect($task->toArray())->except(['description'])->toArray();

        event(new ProjectManagementUpdated('task.created', [
            'task' => $broadcastPayload,
        ], $task->project_id));

        foreach ($task->assignees as $assignee) {
            if ($assignee->id !== auth()->id()) {
                ProjectManagementNotifier::notifyUser($assignee, 'pm_task_assigned', [
                    'title' => 'New Task Assigned',
                    'body' => "You have been assigned to: {$task->title}",
                    'url' => "/dashboard/project-management/projects/{$task->project_id}?taskId={$task->id}",
                    'task_id' => $task->id,
                    'project_id' => $task->project_id,
                ], auth()->id());
            }
        }

        return response()->json($task, 201);
    }

    public function show($id)
    {
        $task = Task::with([
            'project:id,name,project_manager_id,created_by',
            'column:id,name,is_done',
            'assignees:id,name,email,avatar_path,two_factor_confirmed_at',
            'creator:id,name,email,avatar_path,two_factor_confirmed_at',
            'checklists',
            'comments.user:id,name,avatar_path,two_factor_confirmed_at',
            'attachments.fileEntry',
            'timeLogs.user:id,name,avatar_path,two_factor_confirmed_at'
        ])->findOrFail($id);

        return response()->json($task);
    }

    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'column_id' => 'sometimes|required|exists:pm_columns,id',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'priority' => 'sometimes|required|string|in:low,medium,high,urgent',
            'due_date' => 'sometimes|required|date',
            'assignees' => 'sometimes|required|array|min:1',
            'assignees.*' => 'exists:users,id',
            'order' => 'sometimes|nullable|integer',
            'attachments' => 'nullable|array',
            'progress' => 'sometimes|integer|min:0|max:100',
            'effort' => 'sometimes|nullable|string',
            'tags' => 'sometimes|nullable|array',
            'parent_task_id' => 'nullable|exists:pm_tasks,id',
            'issue_type' => 'nullable|string|in:bug,feature,task,improvement,epic,refactor,debt',
            'story_points' => 'nullable|integer|min:0',
            'environment' => 'nullable|string|in:development,staging,production,qa',
            'pr_url' => 'nullable|url',
            'is_backlog' => 'sometimes|nullable|boolean',
            'sprint_id' => 'sometimes|nullable|exists:pm_sprints,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $project = $task->project;

        if (!empty($validated['due_date'])) {
            $dueDate = \Carbon\Carbon::parse($validated['due_date']);
            if ($project->start_date && $dueDate->lt($project->start_date)) {
                return response()->json(['errors' => ['due_date' => ['Due date cannot be before project start date (' . $project->start_date->toDateString() . ')']]], 422);
            }
            if ($project->end_date && $dueDate->gt($project->end_date)) {
                return response()->json(['errors' => ['due_date' => ['Due date cannot be after project end date (' . $project->end_date->toDateString() . ')']]], 422);
            }
        }

        if (!empty($validated['assignees'])) {
            $projectMemberIds = $project->members()->pluck('user_id')->toArray();
            foreach ($validated['assignees'] as $userId) {
                if (!in_array($userId, $projectMemberIds)) {
                    return response()->json(['errors' => ['assignees' => ['One or more selected users are not members of this project.']]], 422);
                }
            }
        }

        $oldColumnId = $task->getOriginal('column_id');

        if (isset($validated['column_id'])) {
            $this->ensureColumnBelongsToProject($validated['column_id'], $task->project_id);
        }

        $oldAssigneeIds = $task->assignees()->pluck('users.id')->toArray();

        DB::transaction(function () use ($task, $validated) {
            $task->update(collect($validated)->except(['assignees', 'attachments'])->toArray());
            
            if (isset($validated['assignees'])) {
                $task->assignees()->sync($validated['assignees']);
            }

            if (isset($validated['attachments'])) {
                // Synchronize pm_task_attachments table
                $attachmentFileIds = collect($validated['attachments'])->map(fn($a) => $a['file_id'] ?? $a['id'] ?? null)->filter()->toArray();
                
                // Remove attachments not in the list
                $task->attachments()->whereNotIn('file_entry_id', $attachmentFileIds)->delete();
                
                // Add new attachments
                foreach ($attachmentFileIds as $fileId) {
                    $task->attachments()->updateOrCreate(
                        ['file_entry_id' => $fileId],
                        ['user_id' => auth()->id()]
                    );
                }

                // Also update the JSON column for fallback/compatibility if needed
                $task->update(['attachments' => $validated['attachments']]);
            }
        });

        $task->load(['assignees:id,name,email,avatar_path,two_factor_confirmed_at', 'creator:id,name,email,avatar_path,two_factor_confirmed_at', 'column:id,name,is_done', 'project:id,name,project_manager_id,created_by']);

        $broadcastPayload = collect($task->toArray())->except(['description'])->toArray();

        event(new ProjectManagementUpdated('task.updated', [
            'task' => $broadcastPayload,
        ], $task->project_id));

        foreach ($task->assignees as $assignee) {
            if ($assignee->id !== auth()->id() && !in_array($assignee->id, $oldAssigneeIds)) {
                ProjectManagementNotifier::notifyUser($assignee, 'pm_task_assigned', [
                    'title' => 'Task Assigned',
                    'body' => "You have been assigned to: {$task->title}",
                    'url' => "/dashboard/project-management/projects/{$task->project_id}?taskId={$task->id}",
                    'task_id' => $task->id,
                    'project_id' => $task->project_id,
                ], auth()->id());
            }
        }

        if ($task->column?->is_done && (string) $oldColumnId !== (string) $task->column_id) {
            $task->loadMissing([
                'project.members.user:id,name,email,avatar_path,two_factor_confirmed_at',
                'project.projectManager:id,name,avatar_path,two_factor_confirmed_at',
                'creator:id,name,email,avatar_path,two_factor_confirmed_at',
            ]);
            ProjectManagementNotifier::notifyTaskStakeholders($task, 'pm_task_completed', [
                'title' => 'Task Completed',
                'body' => "Task completed: {$task->title}",
                'url' => "/dashboard/project-management/projects/{$task->project_id}?taskId={$task->id}",
                'task_id' => $task->id,
                'project_id' => $task->project_id,
            ], auth()->id());
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
                Task::where('column_id', $oldColumnId)
                    ->where('order', '>', $oldOrder)
                    ->decrement('order');

                Task::where('column_id', $newColumnId)
                    ->where('order', '>=', $newOrder)
                    ->increment('order');
            }

            $task->update([
                'column_id' => $newColumnId,
                'order' => $newOrder,
            ]);

            return $task->fresh(['assignees:id,name,email,avatar_path,two_factor_confirmed_at', 'creator:id,name,email,avatar_path,two_factor_confirmed_at', 'column:id,name,is_done', 'project:id,name,project_manager_id,created_by']);
        });

        event(new ProjectManagementUpdated('task.moved', [
            'task' => $task->toArray(),
            'from_column_id' => $oldColumnId,
            'to_column_id' => $newColumnId,
            'order' => $newOrder,
        ], $task->project_id));

        if ($task->column?->is_done && (string) $oldColumnId !== (string) $newColumnId) {
            $task->loadMissing([
                'project.members.user:id,name,email,avatar_path,two_factor_confirmed_at',
                'project.projectManager:id,name,avatar_path,two_factor_confirmed_at',
                'creator:id,name,email,avatar_path,two_factor_confirmed_at',
            ]);
            ProjectManagementNotifier::notifyTaskStakeholders($task, 'pm_task_completed', [
                'title' => 'Task Completed',
                'body' => "Task completed: {$task->title}",
                'url' => "/dashboard/project-management/projects/{$task->project_id}?taskId={$task->id}",
                'task_id' => $task->id,
                'project_id' => $task->project_id,
            ], auth()->id());
        }

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

    public function updateComment(Request $request, $commentId)
    {
        $comment = TaskComment::findOrFail($commentId);

        if ($comment->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'content' => 'nullable|string',
            'attachments' => 'nullable|array',
        ]);

        $comment->update([
            'content' => $validated['content'] ?? $comment->content,
            'attachments' => $validated['attachments'] ?? $comment->attachments,
        ]);

        $comment->load(['user:id,name,avatar_path,two_factor_confirmed_at']);

        event(new ProjectManagementUpdated('comment.updated', [
            'comment' => $comment->toArray(),
            'task_id' => $comment->task_id,
        ], $comment->task?->project_id));

        return response()->json($comment);
    }

    public function destroyComment(Request $request, $commentId)
    {
        $type = $request->input('type', 'me');
        $userId = auth()->id();
        $currentUserId = (string)$userId;

        \Log::error("DEBUG SingleDelete", [
            'commentId' => $commentId,
            'type' => $type,
            'userId' => $userId,
            'db_exists' => TaskComment::where('id', $commentId)->exists()
        ]);

        /** @var TaskComment $comment */
        $comment = TaskComment::with(['task.project'])->findOrFail($commentId);

        $projectId = $comment->task?->project_id;
        $taskId = $comment->task_id;
        $commentOwnerId = (string)$comment->user_id;
        $projectManagerId = (string)($comment->task?->project?->project_manager_id);
        $projectCreatorId = (string)($comment->task?->project?->created_by);
        $taskCreatorId = (string)($comment->task?->created_by);

        $isOwner = $commentOwnerId === $currentUserId;
        $isManager = $projectManagerId === $currentUserId;
        $isProjectCreator = $projectCreatorId === $currentUserId;
        $isTaskCreator = $taskCreatorId === $currentUserId;

        \Log::info("Permissions check", [
            'isOwner' => $isOwner,
            'isManager' => $isManager,
            'isProjectCreator' => $isProjectCreator,
            'isTaskCreator' => $isTaskCreator,
        ]);

        if ($type === 'everyone') {
            if (!$isOwner && !$isManager && !$isProjectCreator && !$isTaskCreator) {
                return response()->json(['message' => 'Unauthorized to delete for everyone'], 403);
            }
            
            $comment->is_deleted_for_everyone = true;
            $comment->content = '<i>Message deleted</i>';
            $comment->attachments = [];
            $comment->save();

            event(new ProjectManagementUpdated('comment.updated', [
                'comment' => $comment->load('user'),
                'task_id' => $taskId,
            ], $projectId));
            
            return response()->json(['message' => 'Message deleted for everyone']);
        } else {
            $hidden = $comment->hidden_for_user_ids ?? [];
            if (!in_array($userId, $hidden)) {
                $hidden[] = $userId;
                $comment->hidden_for_user_ids = $hidden;
                $comment->save();
            }

            event(new ProjectManagementUpdated('comment.updated', [
                'comment' => $comment->load('user'),
                'task_id' => $taskId,
            ], $projectId));

            return response()->json(['message' => 'Message hidden for you']);
        }
    }

    public function bulkDestroyComments(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer',
            'type' => 'required|string|in:me,everyone',
        ]);

        $ids = $request->input('ids');
        $type = $request->input('type');
        $userId = auth()->id();
        
        \Log::info("BulkDestroyComments attempt", [
            'ids' => $ids,
            'type' => $type,
            'userId' => $userId
        ]);

        $comments = TaskComment::with(['task.project'])->whereIn('id', $ids)->get();
        
        \Log::error("DEBUG BulkDelete", [
            'ids_input' => $ids,
            'userId' => $userId,
            'db_count' => TaskComment::count(),
            'found_count' => $comments->count(),
            'found_ids' => $comments->pluck('id')->toArray(),
            'first_comment' => TaskComment::first()
        ]);

        $results = [
            'everyone' => [],
            'me' => [],
            'failed' => [],
        ];

        // Track IDs that were found
        $foundIds = $comments->pluck('id')->toArray();
        $results['failed'] = array_values(array_diff($ids, $foundIds));

        \DB::transaction(function () use ($comments, $type, $userId, &$results) {
            foreach ($comments as $comment) {
                /** @var TaskComment $comment */
                $projectId = $comment->task?->project_id;
                $taskId = $comment->task_id;

                // Cast to string/int for safe comparison
                $currentUserId = (string)$userId;
                $commentOwnerId = (string)$comment->user_id;
                $projectManagerId = (string)($comment->task?->project?->project_manager_id);
                $projectCreatorId = (string)($comment->task?->project?->created_by);
                $taskCreatorId = (string)($comment->task?->created_by);

                $isOwner = $commentOwnerId === $currentUserId;
                $isManager = $projectManagerId === $currentUserId;
                $isProjectCreator = $projectCreatorId === $currentUserId;
                $isTaskCreator = $taskCreatorId === $currentUserId;

                $canDeleteEveryone = $isOwner || $isManager || $isProjectCreator || $isTaskCreator;

                $targetType = $type;
                if ($type === 'everyone' && !$canDeleteEveryone) {
                    $targetType = 'me';
                }

                if ($targetType === 'everyone') {
                    $comment->is_deleted_for_everyone = true;
                    $comment->content = 'This message was deleted';
                    $comment->attachments = [];
                    $comment->save();
                    $results['everyone'][] = $comment->id;
                } else {
                    $hidden = $comment->hidden_for_user_ids ?? [];
                    if (!in_array($userId, $hidden)) {
                        $hidden[] = $userId;
                        $comment->hidden_for_user_ids = $hidden;
                        $comment->save();
                    }
                    $results['me'][] = $comment->id;
                }

                // Fire event for real-time updates
                event(new ProjectManagementUpdated('comment.updated', [
                    'comment' => $comment->load('user'),
                    'task_id' => $taskId,
                ], $projectId));
            }
        });

        $totalProcessed = count($results['everyone']) + count($results['me']);
        
        return response()->json([
            'message' => "Successfully processed {$totalProcessed} messages",
            'everyone_count' => count($results['everyone']),
            'me_count' => count($results['me']),
            'failed_count' => count($results['failed']),
            'failed_ids' => $results['failed'],
            'results' => $results
        ]);
    }


    public function storeComment(Request $request, $taskId)
    {
        $task = Task::findOrFail($taskId);
        
        $validated = $request->validate([
            'content' => 'nullable|string', 
            'parent_id' => 'nullable|exists:pm_task_comments,id',
            'attachments' => 'nullable|array',
        ]);

        // Prevent empty submissions: Comment must have either content or attachments
        if (empty($validated['content']) && empty($validated['attachments'])) {
            return response()->json(['message' => 'Comment must contain text or attachments.'], 422);
        }

        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => auth()->id(),
            'parent_id' => $validated['parent_id'] ?? null,
            'content' => $validated['content'] ?? '',
            'attachments' => $validated['attachments'] ?? null,
        ]);

        $comment->load('user:id,name,email,avatar_path,two_factor_confirmed_at');

        event(new ProjectManagementUpdated('comment.created', [
            'comment' => $comment->toArray(),
            'task_id' => $task->id,
        ], $task->project_id));

        $task->loadMissing([
            'assignees:id,name,email,avatar_path,two_factor_confirmed_at',
            'creator:id,name,email,avatar_path,two_factor_confirmed_at',
            'project.members.user:id,name,email,avatar_path,two_factor_confirmed_at',
            'project.projectManager:id,name,avatar_path,two_factor_confirmed_at',
        ]);
        ProjectManagementNotifier::notifyTaskStakeholders($task, 'pm_task_comment', [
            'title' => 'New Task Comment',
            'body' => "New comment on task: {$task->title}",
            'url' => "/dashboard/project-management/projects/{$task->project_id}?taskId={$task->id}",
            'task_id' => $task->id,
            'project_id' => $task->project_id,
            'sender_id' => auth()->id(),
        ], auth()->id());

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