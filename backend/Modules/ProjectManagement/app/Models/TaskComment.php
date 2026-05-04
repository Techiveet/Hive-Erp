<?php

namespace Modules\ProjectManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Identity\Models\User;

class TaskComment extends Model
{
    protected $table = 'pm_task_comments';

    protected $fillable = [
        'task_id',
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

    public function parent()
    {
        return $this->belongsTo(TaskComment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(TaskComment::class, 'parent_id')->with(['user', 'replies']);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}