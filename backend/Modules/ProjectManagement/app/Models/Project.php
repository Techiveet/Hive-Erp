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
        'start_date',
        'end_date',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected $appends = ['progress', 'tasks_count'];

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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function boards()
    {
        return $this->hasMany(Board::class)->orderBy('order');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
