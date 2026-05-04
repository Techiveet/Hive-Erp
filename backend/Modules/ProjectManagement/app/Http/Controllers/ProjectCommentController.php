<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Models\ProjectComment;
use Modules\ProjectManagement\Events\ProjectManagementUpdated;
use Modules\ProjectManagement\Support\ProjectManagementNotifier;

class ProjectCommentController extends Controller
{
    public function index(Request $request, $projectId)
    {
        $project = Project::findOrFail($projectId);
        
        $comments = $project->comments()
            ->with(['user:id,name,avatar_path,two_factor_confirmed_at'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json($comments);
    }

    public function store(Request $request, $projectId)
    {
        // FIX 1: Make content nullable to allow file-only replies
        $validated = $request->validate([
            'content' => 'nullable|string',
            'parent_id' => 'nullable|exists:pm_project_comments,id',
            'attachments' => 'nullable|array',
        ]);

        // FIX 2: Ensure they at least submit text OR an attachment
        if (empty($validated['content']) && empty($validated['attachments'])) {
            return response()->json(['message' => 'Comment must contain text or attachments.'], 422);
        }

        $project = Project::findOrFail($projectId);

        $comment = ProjectComment::create([
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'parent_id' => $validated['parent_id'] ?? null,
            'content' => $validated['content'] ?? '',
            'attachments' => $validated['attachments'] ?? [],
        ]);

        $comment->load(['user:id,name,avatar_path,two_factor_confirmed_at']);

        event(new ProjectManagementUpdated('project.comment_created', [
            'comment' => $comment->toArray(),
        ], $project->id));

        ProjectManagementNotifier::notifyProjectMembers($project, 'pm_project_comment_added', [
            'title' => 'New Project Discussion',
            'body' => auth()->user()->name . " added a comment to: {$project->name}",
            'url' => "/dashboard/project-management/projects/{$project->id}",
            'project_id' => $project->id,
        ], auth()->id());

        return response()->json($comment, 201);
    }

    public function update(Request $request, $id)
    {
        $comment = ProjectComment::findOrFail($id);

        if ($comment->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'content' => 'nullable|string',
            'attachments' => 'nullable|array',
        ]);

        if (empty($validated['content']) && empty($validated['attachments']) && empty($comment->attachments)) {
            return response()->json(['message' => 'Comment must contain text or attachments.'], 422);
        }

        $comment->update([
            'content' => $validated['content'] ?? '',
            'attachments' => $validated['attachments'] ?? $comment->attachments,
        ]);

        $comment->load(['user:id,name,avatar_path,two_factor_confirmed_at']);

        event(new ProjectManagementUpdated('project.comment_updated', [
            'comment' => $comment->toArray(),
        ], $comment->project_id));

        return response()->json($comment);
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type', 'me');
        $userId = auth()->id();
        $currentUserId = (string)$userId;

        \Log::info("ProjectComment Delete", [
            'id' => $id,
            'type' => $type,
            'userId' => $userId
        ]);

        $comment = ProjectComment::with(['project'])->findOrFail($id);
        
        $projectId = $comment->project_id;
        $commentOwnerId = (string)$comment->user_id;
        $projectManagerId = (string)($comment->project?->project_manager_id);
        $projectCreatorId = (string)($comment->project?->created_by);

        $isOwner = $commentOwnerId === $currentUserId;
        $isManager = $projectManagerId === $currentUserId;
        $isProjectCreator = $projectCreatorId === $currentUserId;

        if ($type === 'everyone') {
            if (!$isOwner && !$isManager && !$isProjectCreator) {
                return response()->json(['message' => 'Unauthorized to delete for everyone'], 403);
            }
            
            $comment->is_deleted_for_everyone = true;
            $comment->content = 'This message was deleted';
            $comment->attachments = [];
            $comment->save();

            event(new ProjectManagementUpdated('project.comment_updated', [
                'comment' => $comment->load('user'),
            ], $projectId));
            
            return response()->json(['message' => 'Message deleted for everyone']);
        } else {
            $hidden = $comment->hidden_for_user_ids ?? [];
            if (!in_array($userId, $hidden)) {
                $hidden[] = $userId;
                $comment->hidden_for_user_ids = $hidden;
                $comment->save();
            }

            event(new ProjectManagementUpdated('project.comment_updated', [
                'comment' => $comment->load('user'),
            ], $projectId));

            return response()->json(['message' => 'Message hidden for you']);
        }
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required',
            'type' => 'required|string|in:me,everyone',
        ]);

        $ids = $request->input('ids');
        $type = $request->input('type');
        $userId = auth()->id();
        $currentUserId = (string)$userId;

        // More robust ID finding
        $comments = ProjectComment::with(['project'])->whereIn('id', $ids)->get();
        
        \Log::info("ProjectComment BulkDelete Execution", [
            'input_ids' => $ids,
            'found_count' => $comments->count(),
            'found_ids' => $comments->pluck('id')->toArray(),
        ]);

        $results = [
            'everyone' => [],
            'me' => [],
            'failed' => [],
        ];

        $foundIds = $comments->pluck('id')->map(fn($id) => (int)$id)->toArray();
        $inputIds = array_map(fn($id) => (int)$id, $ids);
        $results['failed'] = array_values(array_diff($inputIds, $foundIds));

        \DB::transaction(function () use ($comments, $type, $userId, $currentUserId, &$results) {
            foreach ($comments as $comment) {
                /** @var ProjectComment $comment */
                $projectId = $comment->project_id;
                $commentOwnerId = (string)$comment->user_id;
                $projectManagerId = (string)($comment->project?->project_manager_id);
                $projectCreatorId = (string)($comment->project?->created_by);

                $isOwner = $commentOwnerId === $currentUserId;
                $isManager = $projectManagerId === $currentUserId;
                $isProjectCreator = $projectCreatorId === $currentUserId;

                $canDeleteEveryone = $isOwner || $isManager || $isProjectCreator;

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

                event(new ProjectManagementUpdated('project.comment_updated', [
                    'comment' => $comment->load('user'),
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
}