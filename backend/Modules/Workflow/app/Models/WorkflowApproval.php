<?php

namespace Modules\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Models\User;

class WorkflowApproval extends Model
{
    protected $table = 'workflow_approvals';

    protected $fillable = [
        'approvable_id',
        'approvable_type',
        'user_id',
        'role_id',
        'sequence',
        'department',
        'status',
        'notes',
        'requested_by',
        'assigned_at',
        'actioned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'actioned_at' => 'datetime',
    ];

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(ApprovalRole::class, 'role_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
