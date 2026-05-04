<?php

namespace Modules\Hospitality\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistEntry extends Model
{
    use HasFactory;

    protected $table = 'hospitality_waitlist';

    protected $fillable = [
        'customer_name',
        'customer_phone',
        'party_size',
        'preferred_zone',
        'notes',
        'status',
        'notified_at',
        'seated_at',
        'estimated_wait_minutes',
        'reservation_id',
        'metadata',
    ];

    protected $casts = [
        'party_size' => 'integer',
        'estimated_wait_minutes' => 'integer',
        'notified_at' => 'datetime',
        'seated_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }
}
