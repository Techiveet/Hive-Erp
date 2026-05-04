<?php

namespace Modules\Hospitality\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $table = 'hospitality_events';

    protected $fillable = [
        'name',
        'description',
        'event_type',
        'start_at',
        'end_at',
        'is_private',
        'min_guests',
        'max_guests',
        'ticket_price',
        'status',
        'organizer_id',
        'cover_image_url',
        'notes',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_private' => 'boolean',
        'min_guests' => 'integer',
        'max_guests' => 'integer',
        'ticket_price' => 'decimal:2',
    ];

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'organizer_id');
    }

    public function blockedTables(): BelongsToMany
    {
        return $this->belongsToMany(
            Table::class,
            'hospitality_event_tables',
            'event_id',
            'table_id'
        )->withTimestamps();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'event_id');
    }
}
