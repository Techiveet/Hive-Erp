<?php

namespace Modules\Hospitality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;



class ZoneAssignment extends Model
{
    use HasFactory;

    protected $table = 'hospitality_zone_assignments';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'zone_id',
        'shift_date',
    ];

    protected $casts = [
        'shift_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'employee_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }
}
