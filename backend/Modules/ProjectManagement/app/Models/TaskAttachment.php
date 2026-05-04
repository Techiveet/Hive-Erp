<?php

namespace Modules\ProjectManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\FileEntry;
use Modules\Identity\Models\User;

class TaskAttachment extends Model
{
    protected $table = 'pm_task_attachments';

    protected $fillable = [
        'task_id',
        'file_entry_id',
        'user_id',
        'status',
        'reviewed_by',
        'review_note',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function fileEntry()
    {
        return $this->belongsTo(FileEntry::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
