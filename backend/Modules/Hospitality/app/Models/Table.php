<?php

namespace Modules\Hospitality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Table extends Model
{
    use HasFactory;

    protected $table = 'hospitality_tables';

    protected $fillable = [
        'name',
        'capacity',
        'min_spend',
        'status',
        'assigned_staff_id',
        'zone',
        'table_type',
        'is_active',
        'notes',
        'layout_x',
        'layout_y',
        'layout_width',
        'layout_height',
        'layout_rotation',
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
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'assigned_staff_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'table_id');
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'table_id');
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(
            Event::class,
            'hospitality_event_tables',
            'table_id',
            'event_id'
        )->withTimestamps();
    }
}
