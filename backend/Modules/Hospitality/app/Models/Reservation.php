<?php

namespace Modules\Hospitality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    use HasFactory;
    use \Modules\Workflow\Traits\HasDynamicApprovals;

    protected $table = 'hospitality_reservations';

    protected $fillable = [
        'table_id',
        'event_id',
        'customer_profile_id',
        'reservation_code',
        'customer_name',
        'customer_phone',
        'reservation_time',
        'status',
        'guest_count',
        'special_requests',
        'source',
        'expected_spend',
        'assigned_host_id',
        'arrived_at',
        'seated_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'metadata',
    ];
    
    protected $casts = [
        'reservation_time' => 'datetime',
        'arrived_at' => 'datetime',
        'seated_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expected_spend' => 'decimal:2',
        'guest_count' => 'integer',
        'metadata' => 'array',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class, 'table_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_profile_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'assigned_host_id');
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'reservation_id');
    }
}
