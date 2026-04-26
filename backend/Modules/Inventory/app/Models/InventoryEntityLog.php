<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryEntityLog extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $table = 'inventory_entity_logs';

    protected $fillable = [
        'entity_record_id',
        'log_type',
        'description',
        'payload',
        'created_by_id',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function entityRecord(): BelongsTo
    {
        return $this->belongsTo(InventoryEntityRecord::class, 'entity_record_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'created_by_id');
    }
}

