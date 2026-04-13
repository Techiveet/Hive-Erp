<?php

namespace Modules\NightClub\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Table extends Model
{
    use HasFactory;

    protected $table = 'nightclub_tables';

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
    ];

    protected $casts = [
        'capacity' => 'integer',
        'min_spend' => 'decimal:2',
        'is_active' => 'boolean',
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
}
