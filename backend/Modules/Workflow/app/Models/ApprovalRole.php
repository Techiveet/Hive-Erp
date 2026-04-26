<?php

namespace Modules\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Identity\Models\User;

class ApprovalRole extends Model
{
    protected $table = 'approval_roles';

    protected $fillable = [
        'name',
        'description',
        'permissions',
        'is_active',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'approval_role_user')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}