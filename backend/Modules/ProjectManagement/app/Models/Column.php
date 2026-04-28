<?php

namespace Modules\ProjectManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Column extends Model
{
    use HasUuids;

    protected $table = 'pm_columns';

    protected $fillable = [
        'board_id',
        'name',
        'color',
        'order',
        'is_done',
    ];

    protected $casts = [
        'is_done' => 'boolean',
    ];

    public function board()
    {
        return $this->belongsTo(Board::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class)->orderBy('order');
    }
}
