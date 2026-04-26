<?php

namespace Modules\Workflow\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Modules\Workflow\Models\WorkflowApproval;
use Modules\Workflow\Models\WorkflowDefinition;
use Modules\Workflow\Models\ApprovalRole;
use Illuminate\Validation\ValidationException;
use App\Notifications\NewWorkflowApprovalNotification;
use App\Notifications\WorkflowStatusUpdateNotification;
use Illuminate\Support\Facades\Notification;
use Modules\Workflow\Events\WorkflowApprovalRequested;
use Modules\Workflow\Events\WorkflowApprovalStatusChanged;

class WorkflowService
{
    public function getDefinition(string $modelType): ?WorkflowDefinition
    {
        return WorkflowDefinition::where('model_type', $modelType)
            ->where('is_active', true)
            ->first();
    }

    public function assignApprovers(Model $model, array $approverData): array
    {
        if (!method_exists($model, 'approvals')) {
            throw new \InvalidArgumentException("Model must use HasDynamicApprovals trait.");
        }

        return DB::transaction(function () use ($model, $approverData) {
            $approvals = [];
            foreach ($approverData as $data) {
                if (!empty($data['user_id']) || !empty($data['role_id'])) {
                    $approvals[] = $model->requestApproval(
                        $data['user_id'] ?? null,
                        $data['department'] ?? null,
                        $data['role_id'] ?? null,
                        $data['sequence'] ?? 1
                    );
                }
            }
            
            // Notify only the first sequence approvers
            if (!empty($approvals)) {
                $firstSequence = collect($approvals)->min('sequence');
                $initialApprovers = collect($approvals)->where('sequence', $firstSequence);
                
                foreach ($initialApprovers as $approval) {
                    $this->notifyApprover($approval);
                    event(new WorkflowApprovalRequested($approval));
                }
            }

            return $approvals;
        });
    }

    public function applyWorkflow(Model $model): array
    {
        $definition = $this->getDefinition(get_class($model));

        if (!$definition) {
            return [];
        }

        $approverData = $this->resolveApproversFromDefinition($definition);

        if (empty($approverData)) {
            return [];
        }

        return $this->assignApprovers($model, $approverData);
    }

    public function resolveApproversFromDefinition(WorkflowDefinition $definition): array
    {
        $approvers = [];

        if (!empty($definition->approver_ids)) {
            foreach ($definition->approver_ids as $userId) {
                $approvers[] = [
                    'user_id' => $userId,
                    'sequence' => 1,
                ];
            }
        }

        if (!empty($definition->approval_role_ids)) {
            foreach ($definition->approval_role_ids as $roleId) {
                $approvers[] = [
                    'role_id' => $roleId,
                    'sequence' => 1, // Default sequence 1 for roles too
                ];
            }
        }

        return $approvers;
    }

    public function actionApproval(WorkflowApproval $approval, string $status, ?string $notes = null): WorkflowApproval
    {
        return DB::transaction(function () use ($approval, $status, $notes) {
            $approval->update([
                'status' => $status,
                'notes' => $notes,
                'actioned_at' => now(),
            ]);

            $model = $approval->approvable;

            if ($status === 'approved') {
                $freshModel = $model->fresh();
                if ($freshModel && method_exists($freshModel, 'isFullyApproved') && $freshModel->isFullyApproved()) {
                    $this->onModelFullyApproved($freshModel);
                    $this->notifyRequester($approval, 'approved');
                } else {
                    // Notify next step in sequence
                    $this->notifyNextInSequence($approval);
                }
                event(new WorkflowApprovalStatusChanged($approval, 'pending', 'approved'));
            }

            if ($status === 'rejected') {
                if (method_exists($model, 'onWorkflowRejected')) {
                    $model->onWorkflowRejected($approval);
                }
                $this->notifyRequester($approval, 'rejected');
                event(new WorkflowApprovalStatusChanged($approval, 'pending', 'rejected'));
            }

            return $approval;
        });
    }

    protected function notifyApprover(WorkflowApproval $approval): void
    {
        if ($approval->user_id) {
            $user = \Modules\Identity\Models\User::find($approval->user_id);
            if ($user) {
                $user->notify(new NewWorkflowApprovalNotification($approval));
            }
        } elseif ($approval->role_id) {
            $role = ApprovalRole::with('users')->find($approval->role_id);
            if ($role && $role->users->isNotEmpty()) {
                Notification::send($role->users, new NewWorkflowApprovalNotification($approval));
            }
        }
    }

    protected function notifyNextInSequence(WorkflowApproval $currentApproval): void
    {
        $nextApprovals = WorkflowApproval::where('approvable_type', $currentApproval->approvable_type)
            ->where('approvable_id', $currentApproval->approvable_id)
            ->where('sequence', '>', $currentApproval->sequence)
            ->where('status', 'pending')
            ->orderBy('sequence', 'asc')
            ->get();

        if ($nextApprovals->isEmpty()) {
            return;
        }

        $nextSequence = $nextApprovals->first()->sequence;
        $toNotify = $nextApprovals->where('sequence', $nextSequence);

        foreach ($toNotify as $approval) {
            $this->notifyApprover($approval);
        }
    }

    protected function notifyRequester(WorkflowApproval $approval, string $status): void
    {
        if ($approval->requested_by) {
            $requester = \Modules\Identity\Models\User::find($approval->requested_by);
            if ($requester) {
                $requester->notify(new WorkflowStatusUpdateNotification($approval, $status));
            }
        }
    }

    protected function onModelFullyApproved(Model $model): void
    {
        if (method_exists($model, 'onWorkflowFullyApproved')) {
            $model->onWorkflowFullyApproved();
        }
    }
}