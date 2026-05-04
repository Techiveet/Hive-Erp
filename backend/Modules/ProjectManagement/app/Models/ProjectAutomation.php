<?php

namespace Modules\ProjectManagement\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectAutomation extends Model
{
    protected $table = 'pm_automations';

    protected $fillable = [
        'project_id',
        'name',
        'trigger',
        'conditions',
        'action',
        'action_data',
        'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'action_data' => 'array',
        'is_active' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
