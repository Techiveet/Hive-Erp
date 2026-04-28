<?php

namespace Modules\ProjectManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Board extends Model
{
    use HasUuids;

    protected $table = 'pm_boards';

    protected $fillable = [
        'project_id',
        'name',
        'order',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function columns()
    {
        return $this->hasMany(Column::class)->orderBy('order');
    }
}
