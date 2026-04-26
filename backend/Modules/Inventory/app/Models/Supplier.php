<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Models\Concerns\BelongsToInventoryTenant;
use Modules\Workflow\Traits\HasDynamicApprovals;

class Supplier extends Model
{
    use HasFactory;
    use BelongsToInventoryTenant;
    use HasDynamicApprovals;

    protected $connection = 'central';

    protected $table = 'suppliers';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'email',
        'phone',
        'address',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'supplier_id');
    }
}

