<?php

namespace Modules\MailBox\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Support\EncryptsCommunicationAttributes;

class MailMessage extends Model
{
    use EncryptsCommunicationAttributes;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'sender_id',
        'subject',
        'body',
        'status',
        'draft_recipients',
    ];

    protected $hidden = [
        'subject_encrypted',
        'body_encrypted',
    ];

    protected $casts = [
        'draft_recipients' => 'array',
    ];

    public function sender()
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'sender_id');
    }

    public function participants()
    {
        return $this->hasMany(MailParticipant::class);
    }

    public function getSubjectAttribute(mixed $value): ?string
    {
        return $this->getEncryptedCommunicationStringValue(
            $value,
            $this->attributes['subject_encrypted'] ?? null,
            'mail.message.subject'
        );
    }

    public function setSubjectAttribute(mixed $value): void
    {
        $this->setEncryptedCommunicationStringValue(
            'subject',
            'subject_encrypted',
            $value,
            'mail.message.subject'
        );
    }

    public function getBodyAttribute(mixed $value): ?string
    {
        return $this->getEncryptedCommunicationStringValue(
            $value,
            $this->attributes['body_encrypted'] ?? null,
            'mail.message.body'
        );
    }

    public function setBodyAttribute(mixed $value): void
    {
        $this->setEncryptedCommunicationStringValue(
            'body',
            'body_encrypted',
            $value,
            'mail.message.body'
        );
    }
}
