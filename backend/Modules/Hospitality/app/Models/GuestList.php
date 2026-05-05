<?php

namespace Modules\Hospitality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;


class GuestList extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hospitality_guest_lists';

    protected $fillable = [
        'tenant_id',
        'promoter_id',
        'guest_name',
        'expected_party_size',
        'actual_arrived_count',
        'status',
    ];

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'promoter_id');
    }
}
