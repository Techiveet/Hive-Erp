<?php

namespace Modules\Hospitality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;


class PromoterCommission extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hospitality_promoter_commissions';

    protected $fillable = [
        'tenant_id',
        'promoter_id',
        'date',
        'total_guests_brought',
        'commission_earned',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'commission_earned' => 'decimal:2',
    ];

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'promoter_id');
    }
}
