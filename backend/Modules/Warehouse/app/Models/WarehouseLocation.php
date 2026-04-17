<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Warehouse\Models\Concerns\BelongsToWarehouseTenant;

class WarehouseLocation extends Model
{
    use HasFactory, BelongsToWarehouseTenant;

    protected $table = 'warehouse_locations';

    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'parent_id',
        'type',
        'code',
        'name',
        'description',
        'max_weight',
        'max_volume',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'max_weight' => 'decimal:2',
        'max_volume' => 'decimal:2',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class, 'parent_id');
    }
}
