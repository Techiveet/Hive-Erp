<?php

namespace Modules\Workflow\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Workflow\Models\WorkflowApproval;

trait HasDynamicApprovals
{
    /**
     * Get all approvals for the model.
     */
    public function approvals(): MorphMany
    {
        return $this->morphMany(WorkflowApproval::class, 'approvable');
    }

    /**
     * Request approval from a specific user.
     */
    public function requestApproval(?int $userId = null, ?string $department = null, ?int $roleId = null, int $sequence = 1): WorkflowApproval
    {
        return $this->approvals()->create([
            'user_id' => $userId,
            'role_id' => $roleId,
            'sequence' => $sequence,
            'department' => $department,
            'status' => 'pending',
            'requested_by' => auth()->id(),
            'assigned_at' => now(),
        ]);
    }

    /**
     * Check if the model is fully approved (all assigned approvers have approved).
     */
    public function isFullyApproved(): bool
    {
        $approvals = $this->approvals;

        if ($approvals->isEmpty()) {
            return true; // Or false, depending on if approvals are mandatory.
        }

        return $approvals->every(fn ($approval) => $approval->status === 'approved');
    }

    /**
     * Check if there are any rejections.
     */
    public function hasAnyRejection(): bool
    {
        return $this->approvals()->where('status', 'rejected')->exists();
    }

    /**
     * Get pending approvals.
     */
    public function pendingApprovals()
    {
        return $this->approvals()->where('status', 'pending');
    }

    /**
     * Helper to check if a specific user can approve.
     */
    public function canUserApprove(int $userId): bool
    {
        return $this->approvals()
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->exists();
    }

    /**
     * Get a summary of approval statuses grouped by department or user.
     */
    public function getApprovalSummary(): array
    {
        return $this->approvals()
            ->with('user:id,name,email')
            ->get()
            ->map(fn ($approval) => [
                'id' => $approval->id,
                'user' => $approval->user,
                'department' => $approval->department,
                'status' => $approval->status,
                'assigned_at' => $approval->assigned_at,
                'actioned_at' => $approval->actioned_at,
                'notes' => $approval->notes,
            ])
            ->toArray();
    }

    /**
     * Check if a specific department has approved.
     */
    public function hasDepartmentApproved(string $department): bool
    {
        $approvals = $this->approvals()->where('department', $department)->get();
        
        if ($approvals->isEmpty()) {
            return false;
        }

        return $approvals->every(fn ($a) => $a->status === 'approved');
    }

    /**
     * Get the current workflow status of the model.
     */
    public function getWorkflowStatusAttribute(): ?string
    {
        $approvals = $this->approvals()->get();

        if ($approvals->isEmpty()) {
            return null;
        }

        if ($approvals->contains('status', 'rejected')) {
            return 'rejected';
        }

        if ($approvals->every(fn ($a) => $a->status === 'approved')) {
            return 'approved';
        }

        return 'pending';
    }
}
