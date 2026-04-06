<?php

namespace Modules\MailBox\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class MailMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sender_id',
        'subject',
        'body',
        'status',
    ];

    public function sender()
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'sender_id');
    }

    public function participants()
    {
        return $this->hasMany(MailParticipant::class);
    }
}
