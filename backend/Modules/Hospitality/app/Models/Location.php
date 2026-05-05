<?php

namespace Modules\Hospitality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Location extends Model
{
    use HasFactory;

    protected $table = 'hospitality_locations';

    protected $fillable = [
        'tenant_id',
        'zone_id',
        'label',
        'capacity',
        'min_spend',
        'status',
        'assigned_staff_id',
        'table_type',
        'is_active',
        'notes',
        'layout_x',
        'layout_y',
        'layout_width',
        'layout_height',
        'layout_rotation',
        'grid_position',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'min_spend' => 'decimal:2',
        'is_active' => 'boolean',
        'layout_x' => 'decimal:2',
        'layout_y' => 'decimal:2',
        'layout_width' => 'decimal:2',
        'layout_height' => 'decimal:2',
        'layout_rotation' => 'decimal:2',
        'grid_position' => 'json',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'assigned_staff_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'location_id');
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'location_id');
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(
            Event::class,
            'hospitality_event_locations',
            'location_id',
            'event_id'
        )->withTimestamps();
    }
}
