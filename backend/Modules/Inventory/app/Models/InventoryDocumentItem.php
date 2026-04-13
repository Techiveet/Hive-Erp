<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryDocumentItem extends Model
{
    use HasFactory;

    protected $table = 'inventory_document_items';

    protected $fillable = [
        'document_id',
        'inventory_item_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'total_price',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class, 'document_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}

