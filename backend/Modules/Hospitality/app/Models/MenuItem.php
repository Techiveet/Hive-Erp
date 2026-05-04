<?php

namespace Modules\Hospitality\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use HasFactory;

    protected $table = 'hospitality_menu_items';

    protected $fillable = [
        'category_id',
        'inventory_item_id',
        'name',
        'slug',
        'description',
        'price',
        'cost_price',
        'is_available',
        'is_featured',
        'preparation_time_minutes',
        'allergens',
        'tags',
        'image_url',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
        'preparation_time_minutes' => 'integer',
        'allergens' => 'array',
        'tags' => 'array',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(ServiceOrderItem::class, 'inventory_item_id', 'inventory_item_id');
    }
}
