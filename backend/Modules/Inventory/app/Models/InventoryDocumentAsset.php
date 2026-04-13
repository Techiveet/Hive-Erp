<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryDocumentAsset extends Model
{
    use HasFactory;

    protected $table = 'inventory_document_assets';

    protected $fillable = [
        'document_id',
        'kind',
        'label',
        'path',
        'signed_payload',
        'uploaded_by_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class, 'document_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\Models\User::class, 'uploaded_by_id');
    }
}

