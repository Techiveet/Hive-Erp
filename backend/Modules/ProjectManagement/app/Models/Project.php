<?php

namespace Modules\ProjectManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Identity\Models\User;

class Project extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'pm_projects';

    protected $fillable = [
        'name',
        'description',
        'status',
        'priority',
        'start_date',
        'end_date',
        'project_manager_id',
        'client_stakeholder',
        'tags',
        'attachments',
        'budget',
        'currency',
        'hourly_rate',
        'estimated_hours',
        'estimated_revenue',
        'created_by',
        'is_template',
        'template_settings',
        'repository_url',
        'tech_stack',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'tags' => 'array',
        'attachments' => 'array',
        'is_template' => 'boolean',
        'template_settings' => 'array',
        'budget' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'estimated_hours' => 'decimal:2',
        'estimated_revenue' => 'decimal:2',
        'tech_stack' => 'array',
    ];

    protected $appends = ['progress', 'tasks_count', 'completed_tasks_count', 'health'];

    public function getHealthAttribute()
    {
        $total = $this->tasks()->count();
        if ($total === 0) {
            return 'green'; // No tasks yet
        }

        $overdueCount = $this->tasks()
            ->where('due_date', '<', now())
            ->whereHas('column', fn ($q) => $q->where('is_done', false))
            ->count();

        if ($overdueCount > ($total * 0.2)) {
            return 'red'; // More than 20% tasks overdue
        }

        if ($overdueCount > 0) {
            return 'yellow'; // Some tasks overdue
        }

        return 'green';
    }

    public function getProgressAttribute()
    {
        $total = $this->tasks()->count();
        if ($total === 0) {
            return 0;
        }

        $completed = $this->tasks()->whereHas('column', function ($query) {
            $query->where('is_done', true);
        })->count();

        return round(($completed / $total) * 100);
    }

    public function getTasksCountAttribute()
    {
        return $this->attributes['tasks_count'] ?? $this->tasks()->count();
    }

    public function getCompletedTasksCountAttribute()
    {
        return $this->attributes['completed_tasks_count'] ?? $this->tasks()->whereHas('column', function ($query) {
            $query->where('is_done', true);
        })->count();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function projectManager()
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function managers()
    {
        return $this->belongsToMany(User::class, 'pm_project_members', 'project_id', 'user_id')
            ->wherePivot('role', 'manager')
            ->withTimestamps();
    }

    public function isManagerById($userId): bool
    {
        if (!$userId) return false;
        
        return (string) $this->project_manager_id === (string) $userId || 
               $this->managers()->where('users.id', $userId)->exists();
    }

    public function members()
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function allMembers()
    {
        return $this->belongsToMany(User::class, 'pm_project_members', 'project_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function boards()
    {
        return $this->hasMany(Board::class)->orderBy('order');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function comments()
    {
        return $this->hasMany(ProjectComment::class)->whereNull('parent_id')->with(['user', 'replies'])->latest();
    }

    public function goals()
    {
        return $this->hasMany(ProjectGoal::class)->orderBy('order');
    }

    public function automations()
    {
        return $this->hasMany(ProjectAutomation::class);
    }

    public function sprints()
    {
        return $this->hasMany(Sprint::class)->latest();
    }
}
