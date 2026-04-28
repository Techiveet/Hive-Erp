<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\ProjectManagement\Models\ProjectMember;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Events\ProjectManagementUpdated;
use Modules\Identity\Models\User;

class MemberController extends Controller
{
    public function store(Request $request, $projectId)
    {
        Project::findOrFail($projectId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|string|in:owner,manager,member,viewer',
        ]);

        $member = ProjectMember::updateOrCreate(
            ['project_id' => $projectId, 'user_id' => $validated['user_id']],
            ['role' => $validated['role']]
        );

        $member->load('user:id,name,email,avatar_path');

        event(new ProjectManagementUpdated('member.updated', [
            'member' => $member->toArray(),
        ], $projectId));

        return response()->json($member, 201);
    }

    public function destroy($projectId, $userId)
    {
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
        $search = $request->input('search');
        $users = User::query()
            ->where('name', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%")
            ->limit(10)
            ->get(['id', 'name', 'email', 'avatar_path']);

        return response()->json($users);
    }
}
