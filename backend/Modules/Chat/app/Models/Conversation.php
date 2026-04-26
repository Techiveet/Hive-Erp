<?php

namespace Modules\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Models\User;
use Laravel\Scout\Searchable;

class Conversation extends Model
{
    use Searchable;

    public function searchableAs()
    {
        $prefix = function_exists('tenant') && tenant('id')
            ? 'tenant_' . tenant('id') . '_'
            : 'central_';

        return $prefix . $this->getTable();
    }

    protected $fillable = [
        'type',
        'title',
        'avatar_path',
        'created_by',
        'created_at',
        'updated_at',
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
            'title' => $this->title,
            'type' => $this->type,
            'participants' => $this->participants->pluck('name')->toArray(),
        ];
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot([
                'joined_at',
                'last_read_at',
                'encrypted_conversation_key',
                'conversation_key_algorithm',
                'conversation_key_version',
            ])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->oldest();
    }

    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
