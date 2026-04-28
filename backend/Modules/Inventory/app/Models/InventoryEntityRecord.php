<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Modules\Inventory\Models\Concerns\BelongsToInventoryTenant;
use Modules\Workflow\Traits\HasDynamicApprovals;

class InventoryEntityRecord extends Model
{
    use HasFactory, HasDynamicApprovals, BelongsToInventoryTenant;

    protected $connection = 'central';

    protected $table = 'inventory_entity_records';

    protected $fillable = [
        'tenant_id',
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

    /**
     * Handle logic when the record is fully approved via dynamic workflow.
     */
    public function onWorkflowFullyApproved(): void
    {
        if ($this->entity_type === 'batch') {
            // Logic for Shelving a batch
            // This usually involves creating a StockMovement
            $payload = $this->payload;
            
            if (!empty($payload['target_location_id'])) {
                $movement = \Modules\Warehouse\Models\StockMovement::create([
                    'tenant_id' => $this->tenant_id,
                    'product_id' => $payload['product_id'] ?? null,
                    'to_location_id' => $payload['target_location_id'],
                    'type' => 'receive',
                    'quantity' => $payload['quantity'] ?? 0,
                    'batch_number' => $this->code,
                    'reference_type' => self::class,
                    'reference_id' => $this->id,
                    'notes' => 'Automatic shelving movement upon approval.',
                ]);

                // Immediately process since the batch itself was approved
                $movement->process();
            }
        }
    }
}

