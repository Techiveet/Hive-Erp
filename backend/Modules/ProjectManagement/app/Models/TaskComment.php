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
        'content',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
