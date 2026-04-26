<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasFactory;
    use \Modules\Inventory\Models\Concerns\SupportsProductCatalog;
    use \Modules\Workflow\Traits\HasDynamicApprovals;

    protected $connection = 'central';

    protected $table = 'inventory_items';

    protected $fillable = [
        'tenant_id',
        'category_id',
        'sku',
        'name',
        'unit',
        'current_stock',
        'reorder_level',
        'cost_price',
        'selling_price',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'current_stock' => 'decimal:3',
        'reorder_level' => 'decimal:3',
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function toCatalogArray(): array
    {
        return [
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'unit' => $this->unit,
            'quantity' => (float) $this->current_stock,
            'reorder_point' => (int) $this->reorder_level,
            'cost_of_good' => (float) $this->cost_price,
            'sale_price' => (float) $this->selling_price,
            'status' => $this->is_active ? 'published' : 'draft',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'item_id');
    }

    /**
     * Handle logic when the item is fully approved via dynamic workflow.
     */
    public function onWorkflowFullyApproved(): void
    {
        $this->update(['is_active' => true]);
    }
}
