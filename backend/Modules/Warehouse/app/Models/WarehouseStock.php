<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Warehouse\Models\Concerns\BelongsToWarehouseTenant;

class WarehouseStock extends Model
{
    use HasFactory, BelongsToWarehouseTenant;

    protected $table = 'warehouse_stocks';

    protected $fillable = [
        'tenant_id',
        'warehouse_location_id',
        'product_id',
        'batch_number',
        'serial_number',
        'expiry_date',
        'on_hand',
        'reserved',
        'in_transit',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'on_hand' => 'decimal:4',
        'reserved' => 'decimal:4',
        'in_transit' => 'decimal:4',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }
}
