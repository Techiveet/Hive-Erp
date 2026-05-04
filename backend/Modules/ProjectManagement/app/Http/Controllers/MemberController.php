<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Identity\Models\User;
use Modules\ProjectManagement\Models\ProjectMember;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Events\ProjectManagementUpdated;
use Modules\ProjectManagement\Support\ProjectManagementNotifier;

class MemberController extends Controller
{
    public function store(Request $request, $projectId)
    {
        $project = Project::findOrFail($projectId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|string|in:owner,manager,member,viewer',
        ]);

        $member = ProjectMember::updateOrCreate(
            ['project_id' => $projectId, 'user_id' => $validated['user_id']],
            ['role' => $validated['role']]
        );

        $member->load('user:id,name,email,avatar_path,two_factor_confirmed_at');

        event(new ProjectManagementUpdated('member.updated', [
            'member' => $member->toArray(),
        ], $projectId));

        ProjectManagementNotifier::notifyUser($member->user, 'pm_project_member_added', [
            'title' => 'Added to Project',
            'body' => "You were added to project: {$project->name}",
            'url' => "/dashboard/project-management/projects/{$project->id}",
            'project_id' => $project->id,
            'role' => $member->role,
        ], auth()->id());

        return response()->json($member, 201);
    }

    public function destroy($projectId, $userId)
    {
        /** @var ProjectMember $member */
        $member = ProjectMember::where('project_id', $projectId)
            ->where('user_id', $userId)
            ->firstOrFail();
        $payload = $member->toArray();
        
        $member->delete();

        event(new ProjectManagementUpdated('member.deleted', [
            'member' => $payload,
        ], $projectId));

        return response()->json(null, 204);
    }

    public function searchUsers(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $users = User::query()
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'name', 'email', 'avatar_path'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_path' => $user->avatar_path,
            ])
            ->values();

        return response()->json($users);
    }

    public function getGlobalWorkload(Request $request)
    {
        $users = User::whereHas('projectMembers')
            ->with(['tasks' => function($q) {
                $q->whereHas('column', fn($c) => $c->where('is_done', false))
                  ->with('project:id,name');
            }])
            ->get(['id', 'name', 'avatar_path'])
            ->map(function ($user) {
                return $user;
            });

        return response()->json($users);
    }
}
