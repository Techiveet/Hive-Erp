<?php

namespace Modules\ProjectManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Identity\Models\User;

class ProjectComment extends Model
{
    protected $table = 'pm_project_comments';

    protected $fillable = [
        'project_id',
        'user_id',
        'parent_id',
        'content',
        'attachments',
        'is_deleted_for_everyone',
        'hidden_for_user_ids',
    ];

    protected $casts = [
        'attachments' => 'array',
        'hidden_for_user_ids' => 'array',
        'is_deleted_for_everyone' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(ProjectComment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(ProjectComment::class, 'parent_id')->with(['user', 'replies']);
    }
}