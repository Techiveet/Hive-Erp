<?php

namespace Modules\NightClub\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;

class ServiceOrderItem extends Model
{
    use HasFactory;

    protected $table = 'nightclub_service_order_items';

    protected $fillable = [
        'service_order_id',
        'inventory_item_id',
        'inventory_transaction_id',
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
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function inventoryTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id');
    }
}
