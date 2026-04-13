<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryDocument extends Model
{
    use HasFactory;

    protected $table = 'inventory_documents';

    protected $fillable = [
        'type',
        'status',
        'document_number',
        'title',
        'notes',
        'source_document_id',
        'created_by_id',
        'approved_by_id',
        'approved_at',
        'processed_at',
        'workflow_meta',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
        'workflow_meta' => 'array',
    ];

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_document_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryDocumentItem::class, 'document_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(InventoryDocumentAsset::class, 'document_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'created_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'approved_by_id');
    }
}

