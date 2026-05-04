<?php

namespace Modules\Hospitality\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillSplit extends Model
{
    use HasFactory;

    protected $table = 'hospitality_bill_splits';

    protected $fillable = [
        'service_order_id',
        'split_name',
        'amount',
        'tip_amount',
        'payment_method',
        'payment_reference',
        'is_paid',
        'paid_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tip_amount' => 'decimal:2',
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }
}
