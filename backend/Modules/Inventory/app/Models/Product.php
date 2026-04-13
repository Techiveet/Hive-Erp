<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Models\Concerns\BelongsToInventoryTenant;

class Product extends Model
{
    use HasFactory;
    use BelongsToInventoryTenant;

    protected $table = 'products';

    protected $fillable = [
        'tenant_id',
        'name',
        'sku',
        'stock_code',
        'description',
        'product_category_id',
        'supplier_id',
        'parent_product_id',
        'unit',
        'uom',
        'units_per_package',
        'reorder_point',
        'quantity',
        'unit_price',
        'tax_rate',
        'cost_of_good',
        'sale_price',
        'barcode',
        'barcode_path',
        'image',
        'model_3d_path',
        'hs_code',
        'country_of_origin',
        'nutritional_info',
        'attributes',
        'track_inventory',
        'allow_backorders',
        'status',
        'weight',
        'length',
        'width',
        'height',
        'metadata',
    ];

    protected $casts = [
        'units_per_package' => 'integer',
        'reorder_point' => 'integer',
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'cost_of_good' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'track_inventory' => 'boolean',
        'allow_backorders' => 'boolean',
        'nutritional_info' => 'array',
        'attributes' => 'array',
        'metadata' => 'array',
        'weight' => 'decimal:3',
        'length' => 'decimal:3',
        'width' => 'decimal:3',
        'height' => 'decimal:3',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_product_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_product_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tag', 'product_id', 'tag_id')
            ->withTimestamps();
    }
}
