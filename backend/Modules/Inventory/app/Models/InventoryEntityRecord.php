<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryEntityRecord extends Model
{
    use HasFactory;

    protected $table = 'inventory_entity_records';

    protected $fillable = [
        'entity_type',
        'name',
        'code',
        'parent_id',
        'is_active',
        'payload',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'payload' => 'array',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(InventoryEntityLog::class, 'entity_record_id');
    }

    public function qaResults(): HasMany
    {
        return $this->hasMany(InventoryBatchQaResult::class, 'batch_record_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'created_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'updated_by_id');
    }
}

