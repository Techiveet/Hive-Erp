<?php

namespace Modules\MailBox\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class MailParticipant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'mail_message_id',
        'user_id',
        'type',
        'folder',
        'is_read',
        'is_starred',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_starred' => 'boolean',
    ];

    public function message()
    {
        return $this->belongsTo(MailMessage::class, 'mail_message_id');
    }

    public function user()
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'user_id');
    }
}
