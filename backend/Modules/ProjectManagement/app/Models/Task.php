<?php

namespace Modules\ProjectManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Identity\Models\User;

class Task extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'pm_tasks';

    protected $fillable = [
        'project_id',
        'column_id',
        'title',
        'description',
        'priority',
        'due_date',
        'created_by',
        'order',
        'attachments',
        'parent_task_id',
        'tags',
        'issue_type',
        'story_points',
        'environment',
        'pr_url',
        'pr_status',
        'build_status',
        'is_backlog',
        'sprint_id',
        'progress',
        'effort',
    ];

    protected $casts = [
        'due_date' => 'date',
        'attachments' => 'array',
        'tags' => 'array',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function column()
    {
        return $this->belongsTo(Column::class);
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'pm_task_assignees');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function checklists()
    {
        return $this->hasMany(TaskChecklist::class)->orderBy('order');
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class)->whereNull('parent_id')->with(['user', 'replies'])->latest();
    }

    public function attachments()
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function timeLogs()
    {
        return $this->hasMany(TaskTimeLog::class);
    }

    public function parentTask()
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function subTasks()
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    public function sprint()
    {
        return $this->belongsTo(Sprint::class);
    }
}
