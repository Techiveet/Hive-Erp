<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Warehouse\Models\Concerns\BelongsToWarehouseTenant;

class StockMovement extends Model
{
    use HasFactory, BelongsToWarehouseTenant;
    use \Modules\Workflow\Traits\HasDynamicApprovals;

    protected $table = 'warehouse_stock_movements';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'from_location_id',
        'to_location_id',
        'type',
        'quantity',
        'unit_cost',
        'batch_number',
        'serial_number',
        'expiry_date',
        'reference_type',
        'reference_id',
        'notes',
        'performed_by_id',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
    ];

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'to_location_id');
    }

    /**
     * Handle logic when the movement is fully approved via dynamic workflow.
     */
    public function onWorkflowFullyApproved(): void
    {
        // For movements, we might want to trigger actual stock adjustments here 
        // if they weren't done immediately.
    }

    /**
     * Handle logic when the movement is rejected via dynamic workflow.
     */
    public function onWorkflowRejected($approval): void
    {
        // Rejection logic
    }
}
