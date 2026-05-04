<?php

namespace Modules\Hospitality\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCategory extends Model
{
    use HasFactory;

    protected $table = 'hospitality_menu_categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
        'color',
        'icon',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'category_id');
    }
}
