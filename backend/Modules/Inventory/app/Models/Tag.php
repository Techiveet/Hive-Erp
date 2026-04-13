<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Inventory\Models\Concerns\BelongsToInventoryTenant;

class Tag extends Model
{
    use HasFactory;
    use BelongsToInventoryTenant;

    protected $table = 'tags';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_tag', 'tag_id', 'product_id')
            ->withTimestamps();
    }
}

