<?php

namespace Modules\Hospitality\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffShift extends Model
{
    use HasFactory;

    protected $table = 'hospitality_staff_shifts';

    protected $fillable = [
        'staff_id',
        'shift_date',
        'start_at',
        'end_at',
        'zone',
        'role',
        'is_confirmed',
        'notes',
        'created_by_id',
    ];

    protected $casts = [
        'shift_date' => 'date',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_confirmed' => 'boolean',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'staff_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'created_by_id');
    }
}
