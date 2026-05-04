<?php

namespace Modules\ProjectManagement\Support;

use App\Notifications\ProjectManagementNotification;
use Illuminate\Support\Collection;
use Modules\Identity\Models\User;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Models\Task;

class ProjectManagementNotifier
{
    /**
     * @param  string|int|null  $actorId  UUID or int ID of the user performing the action (skipped from notifications).
     */
    public static function notifyUser(?User $user, string $category, array $data, mixed $actorId = null): void
    {
        if (! $user || ($actorId && (string) $user->id === (string) $actorId)) {
            return;
        }

        $user->notify(new ProjectManagementNotification($category, $data));
    }

    public static function notifyProjectMembers(Project $project, string $category, array $data, mixed $actorId = null): void
    {
        self::projectUsers($project, $actorId)
            ->each(fn (User $user) => self::notifyUser($user, $category, $data, $actorId));
    }

    public static function notifyTaskStakeholders(Task $task, string $category, array $data, mixed $actorId = null): void
    {
        $users = collect();

        if ($task->relationLoaded('assignees')) {
            $users = $users->merge($task->assignees);
        } else {
            $users = $users->merge($task->assignees()->get(['id', 'name', 'email', 'avatar_path', 'two_factor_confirmed_at']));
        }

        if ($task->relationLoaded('creator') && $task->creator) {
            $users->push($task->creator);
        } elseif ($task->created_by) {
            $creator = User::query()
                ->select(['id', 'name', 'email', 'avatar_path', 'two_factor_confirmed_at'])
                ->find($task->created_by);
            if ($creator) {
                $users->push($creator);
            }
        }

        if ($task->project) {
            $users = $users->merge(self::projectUsers($task->project, $actorId));
        }

        $users
            ->unique('id')
            ->each(fn (User $user) => self::notifyUser($user, $category, $data, $actorId));
    }

    private static function projectUsers(Project $project, mixed $actorId = null): Collection
    {
        $project->loadMissing([
            'members.user:id,name,email,avatar_path,two_factor_confirmed_at',
            'creator:id,name,email,avatar_path,two_factor_confirmed_at',
            'projectManager:id,name,email,avatar_path,two_factor_confirmed_at',
        ]);

        $users = collect();
        
        if ($project->creator) {
            $users->push($project->creator);
        }
        
        if ($project->projectManager) {
            $users->push($project->projectManager);
        }

        // Include all members (this includes all managers and owners assigned to the project)
        $users = $users->merge($project->members->pluck('user')->filter());

        return $users
            ->filter(fn (User $user) => ! $actorId || (string) $user->id !== (string) $actorId)
            ->unique('id')
            ->values();
    }
}
