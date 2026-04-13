<?php

namespace Modules\NightClub\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceOrder extends Model
{
    use HasFactory;

    protected $table = 'nightclub_service_orders';

    protected $fillable = [
        'order_number',
        'table_id',
        'reservation_id',
        'status',
        'notes',
        'total_amount',
        'served_by_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class, 'table_id');
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
}
