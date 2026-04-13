<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBatchQaResult extends Model
{
    use HasFactory;

    protected $table = 'inventory_batch_qa_results';

    protected $fillable = [
        'batch_record_id',
        'result',
        'notes',
        'tested_at',
        'tested_by_id',
        'payload',
    ];

    protected $casts = [
        'tested_at' => 'datetime',
        'payload' => 'array',
    ];

    public function batchRecord(): BelongsTo
    {
        return $this->belongsTo(InventoryEntityRecord::class, 'batch_record_id');
    }

    public function testedBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'tested_by_id');
    }
}

