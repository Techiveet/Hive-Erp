<?php

namespace Modules\NightClub\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderItem extends Model
{
    use HasFactory;

    protected $table = 'nightclub_service_order_items';

    protected $fillable = [
        'service_order_id',
        'inventory_item_id',
        'inventory_transaction_id',
        'inventory_item_snapshot',
        'inventory_transaction_snapshot',
        'item_name',
        'quantity',
        'unit_price',
        'total_price',
        'stock_deducted',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'stock_deducted' => 'boolean',
        'inventory_item_snapshot' => 'array',
        'inventory_transaction_snapshot' => 'array',
    ];

    protected $appends = [
        'inventory_item',
        'inventory_transaction',
    ];

    protected $hidden = [
        'inventory_item_snapshot',
        'inventory_transaction_snapshot',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    public function getInventoryItemAttribute(): ?array
    {
        return $this->inventory_item_snapshot;
    }

    public function getInventoryTransactionAttribute(): ?array
    {
        return $this->inventory_transaction_snapshot;
    }
}
