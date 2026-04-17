<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Warehouse\Models\Concerns\BelongsToWarehouseTenant;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes, BelongsToWarehouseTenant;

    protected $table = 'warehouse_warehouses';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'type',
        'is_active',
        'address',
        'contact_person',
        'phone',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class, 'warehouse_id');
    }
}
