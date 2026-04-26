<?php

namespace Modules\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Models\User;
use Laravel\Scout\Searchable;
use Modules\Core\Support\EncryptsCommunicationAttributes;

class Message extends Model
{
    use EncryptsCommunicationAttributes;
    use Searchable;

    public function searchableAs()
    {
        $prefix = function_exists('tenant') && tenant('id')
            ? 'tenant_' . tenant('id') . '_'
            : 'central_';

        return $prefix . $this->getTable();
    }

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'type',
        'metadata',
        'is_read',
        'created_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    protected $hidden = [
        'body_encrypted',
        'metadata_encrypted',
    ];

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'body' => $this->body,
            'type' => $this->type,
            'conversation_id' => (int) $this->conversation_id,
            'sender_id' => (int) $this->sender_id,
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function getBodyAttribute(mixed $value): ?string
    {
        return $this->getEncryptedCommunicationStringValue(
            $value,
            $this->attributes['body_encrypted'] ?? null,
            'chat.message.body'
        );
    }

    public function setBodyAttribute(mixed $value): void
    {
        $this->setEncryptedCommunicationStringValue(
            'body',
            'body_encrypted',
            $value,
            'chat.message.body'
        );
    }

    public function getMetadataAttribute(mixed $value): ?array
    {
        return $this->getEncryptedCommunicationArrayValue(
            $value,
            $this->attributes['metadata_encrypted'] ?? null,
            'chat.message.metadata'
        );
    }

    public function setMetadataAttribute(mixed $value): void
    {
        $this->setEncryptedCommunicationArrayValue(
            'metadata',
            'metadata_encrypted',
            $value,
            'chat.message.metadata'
        );
    }
}
