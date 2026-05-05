<?php

namespace Modules\Hospitality\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class ServiceOrder extends Model
{
    use HasFactory;
    use \Modules\Workflow\Traits\HasDynamicApprovals;

    protected $table = 'hospitality_service_orders';

    protected $fillable = [
        'order_number',
        'location_id',
        'reservation_id',
        'status',
        'notes',
        'total_amount',
        'served_by_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    public function servedBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'served_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceOrderItem::class, 'service_order_id');
    }

    public function billSplits(): HasMany
    {
        return $this->hasMany(BillSplit::class, 'service_order_id');
    }
}
