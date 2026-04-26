<?php

namespace Modules\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorkflowDefinition extends Model
{
    protected $table = 'workflow_definitions';

    protected $fillable = [
        'name',
        'model_type',
        'approver_ids',
        'approval_role_ids',
        'required_approvals',
        'trigger_event',
        'is_active',
    ];

    protected $casts = [
        'approver_ids' => 'array',
        'approval_role_ids' => 'array',
        'is_active' => 'boolean',
    ];

    public function approvalRoles(): BelongsToMany
    {
        return $this->belongsToMany(ApprovalRole::class, 'workflow_definition_approval_role', 'workflow_definition_id', 'approval_role_id')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForModel($query, string $modelType)
    {
        return $query->where('model_type', $modelType);
    }
}