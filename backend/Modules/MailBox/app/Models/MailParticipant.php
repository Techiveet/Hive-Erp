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
        'encrypted_message_key',
        'message_key_algorithm',
        'message_key_version',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_starred' => 'boolean',
        'message_key_version' => 'integer',
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
