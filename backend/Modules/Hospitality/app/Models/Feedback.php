<?php

namespace Modules\Hospitality\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;

    protected $table = 'hospitality_feedback';

    protected $fillable = [
        'reservation_id',
        'service_order_id',
        'customer_name',
        'customer_phone',
        'rating',
        'food_rating',
        'service_rating',
        'ambiance_rating',
        'comment',
        'tags',
        'is_published',
        'responded_at',
        'response',
        'responded_by_id',
    ];

    protected $casts = [
        'rating' => 'integer',
        'food_rating' => 'integer',
        'service_rating' => 'integer',
        'ambiance_rating' => 'integer',
        'is_published' => 'boolean',
        'responded_at' => 'datetime',
        'tags' => 'array',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'responded_by_id');
    }
}
