<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $table = 'inventory_transactions';

    protected $fillable = [
        'item_id',
        'direction',
        'type',
        'quantity',
        'balance_after',
        'unit_cost',
        'total_cost',
        'module_source',
        'reference_type',
        'reference_id',
        'notes',
        'performed_by_id',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'balance_after' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'performed_by_id');
    }
}
