<?php

namespace Modules\Inventory\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\InventoryDocument;
use Modules\Inventory\Models\InventoryDocumentAsset;
use Modules\Inventory\Models\InventoryDocumentItem;
use Modules\Workflow\Models\WorkflowApproval;

class InventoryDocumentWorkflowService
{
    public function __construct(
        protected InventoryTransactionService $transactions
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function createDocument(array $payload, array $items): InventoryDocument
    {
        return DB::transaction(function () use ($payload, $items): InventoryDocument {
            $type = (string) $payload['type'];
            $document = InventoryDocument::query()->create([
                'type' => $type,
                'status' => (string) ($payload['status'] ?? InventoryBlueprint::initialStatusFor($type)),
                'document_number' => (string) ($payload['document_number'] ?? $this->generateDocumentNumber($type)),
                'title' => $payload['title'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'source_document_id' => $payload['source_document_id'] ?? null,
                'created_by_id' => auth()->id(),
                'workflow_meta' => is_array($payload['workflow_meta'] ?? null) ? $payload['workflow_meta'] : null,
            ]);

            if (is_array($payload['approvers'] ?? null)) {
                foreach ($payload['approvers'] as $approver) {
                    if (!empty($approver['user_id'])) {
                        $document->requestApproval(
                            (int) $approver['user_id'],
                            $approver['department'] ?? null
                        );
                    }
                }
            }

            $this->syncItems($document, $items);

            return $document->fresh()->load([
                'items.inventoryItem:id,sku,name,unit,current_stock',
                'assets',
                'createdBy:id,name,email',
                'approvedBy:id,name,email',
            ]);
        });
    }

    /**
     * @param array<int, array<string, mixed>>|null $items
     */
    public function updateDocument(InventoryDocument $document, array $payload, ?array $items = null): InventoryDocument
    {
        return DB::transaction(function () use ($document, $payload, $items): InventoryDocument {
            $locked = InventoryDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            $locked->update([
                'title' => array_key_exists('title', $payload) ? $payload['title'] : $locked->title,
                'notes' => array_key_exists('notes', $payload) ? $payload['notes'] : $locked->notes,
                'source_document_id' => array_key_exists('source_document_id', $payload)
                    ? $payload['source_document_id']
                    : $locked->source_document_id,
                'workflow_meta' => is_array($payload['workflow_meta'] ?? null)
                    ? $payload['workflow_meta']
                    : $locked->workflow_meta,
            ]);

            if (is_array($items)) {
                $this->syncItems($locked, $items);
            }

            return $locked->fresh()->load([
                'items.inventoryItem:id,sku,name,unit,current_stock',
                'assets',
                'createdBy:id,name,email',
                'approvedBy:id,name,email',
            ]);
        });
    }

    public function transition(InventoryDocument $document, string $action, array $payload = []): InventoryDocument
    {
        $transition = InventoryBlueprint::transitionFor($document->type, $action);
        if (!$transition) {
            throw ValidationException::withMessages([
                'action' => "Action '{$action}' is not supported for document type '{$document->type}'.",
            ]);
        }

        return DB::transaction(function () use ($document, $action, $payload, $transition): InventoryDocument {
            $locked = InventoryDocument::query()
                ->with(['items.inventoryItem'])
                ->whereKey($document->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateQualityCompliance($locked, $action);

            $allowedFrom = is_array($transition['from'] ?? null) ? $transition['from'] : [];
            if (!in_array($locked->status, $allowedFrom, true)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot {$action} {$locked->type} document from status '{$locked->status}'.",
                ]);
            }

            if (!empty($payload['signature'])) {
                InventoryDocumentAsset::query()->create([
                    'document_id' => $locked->id,
                    'kind' => 'signature',
                    'label' => (string) ($payload['signature_label'] ?? Str::headline($action) . ' Signature'),
                    'signed_payload' => (string) $payload['signature'],
                    'uploaded_by_id' => auth()->id(),
                    'metadata' => is_array($payload['signature_metadata'] ?? null) ? $payload['signature_metadata'] : null,
                ]);
            }

            $stockEffect = is_array($transition['stock_effect'] ?? null)
                ? $transition['stock_effect']
                : null;

            if ($stockEffect) {
                $this->applyStockEffect($locked, $stockEffect, $action, $payload);
            }

            $currentMeta = is_array($locked->workflow_meta) ? $locked->workflow_meta : [];
            $currentMeta['last_action'] = $action;
            $currentMeta['last_action_at'] = now()->toIso8601String();
            $currentMeta['last_action_by'] = auth()->id();

            $targetStatus = (string) ($transition['to'] ?? $locked->status);
            $update = [
                'workflow_meta' => $currentMeta,
            ];

            if ($action === 'approve') {
                if ($locked->approvals()->exists()) {
                    $myApproval = $locked->approvals()
                        ->where('user_id', auth()->id())
                        ->where('status', 'pending')
                        ->first();

                    if ($myApproval) {
                        /** @var \Modules\Workflow\Models\WorkflowApproval $myApproval */
                        $myApproval->update([
                            'status' => 'approved',
                            'actioned_at' => now(),
                            'notes' => $payload['notes'] ?? null,
                        ]);
                    }

                    if ($locked->isFullyApproved()) {
                        $update['status'] = $targetStatus;
                        $update['approved_by_id'] = auth()->id();
                        $update['approved_at'] = now();
                    } else {
                        // Stay in current status if not fully approved
                        $update['status'] = $locked->status;
                    }
                } else {
                    $update['status'] = $targetStatus;
                    $update['approved_by_id'] = auth()->id();
                    $update['approved_at'] = now();
                }
            } else {
                $update['status'] = $targetStatus;
            }

            if (in_array($targetStatus, ['processed', 'received', 'dispatched', 'completed'], true)) {
                $update['processed_at'] = now();
            }

            if (!empty($payload['notes'])) {
                $suffix = trim((string) $payload['notes']);
                $update['notes'] = trim(($locked->notes ? "{$locked->notes}\n\n" : '') . '[' . Str::upper($action) . "] {$suffix}");
            }

            $locked->update($update);

            return $locked->fresh()->load([
                'items.inventoryItem:id,sku,name,unit,current_stock',
                'assets',
                'createdBy:id,name,email',
                'approvedBy:id,name,email',
            ]);
        });
    }

    public function attachAsset(InventoryDocument $document, array $payload): InventoryDocumentAsset
    {
        return InventoryDocumentAsset::query()->create([
            'document_id' => $document->id,
            'kind' => (string) ($payload['kind'] ?? 'attachment'),
            'label' => $payload['label'] ?? null,
            'path' => $payload['path'] ?? null,
            'signed_payload' => $payload['signed_payload'] ?? null,
            'uploaded_by_id' => auth()->id(),
            'metadata' => is_array($payload['metadata'] ?? null) ? $payload['metadata'] : null,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    protected function syncItems(InventoryDocument $document, array $items): void
    {
        $document->items()->delete();

        foreach ($items as $item) {
            $quantity = round((float) ($item['quantity'] ?? 0), 3);
            $unitPrice = isset($item['unit_price']) ? round((float) $item['unit_price'], 2) : null;

            InventoryDocumentItem::query()->create([
                'document_id' => $document->id,
                'inventory_item_id' => $item['inventory_item_id'] ?? null,
                'description' => $item['description'] ?? null,
                'quantity' => $quantity,
                'unit' => (string) ($item['unit'] ?? 'unit'),
                'unit_price' => $unitPrice,
                'total_price' => isset($item['total_price'])
                    ? round((float) $item['total_price'], 2)
                    : ($unitPrice !== null ? round($unitPrice * $quantity, 2) : null),
                'metadata' => is_array($item['metadata'] ?? null) ? $item['metadata'] : null,
            ]);
        }
    }

    protected function applyStockEffect(InventoryDocument $document, array $stockEffect, string $action, array $payload): void
    {
        $direction = (string) ($stockEffect['direction'] ?? 'out');
        $movement = (string) ($stockEffect['movement'] ?? 'adjustment');
        $referenceType = (string) ($stockEffect['reference_type'] ?? $document->type);

        foreach ($document->items as $item) {
            if (!$item->inventory_item_id || (float) $item->quantity <= 0) {
                continue;
            }

            $transactionPayload = [
                'type' => $movement,
                'unit_cost' => $item->unit_price,
                'notes' => $payload['notes'] ?? $document->notes,
                'reference_type' => $referenceType,
                'reference_id' => (string) $document->id,
                'module_source' => 'inventory_workflow',
                'performed_by_id' => auth()->id(),
                'metadata' => [
                    'document_number' => $document->document_number,
                    'document_type' => $document->type,
                    'action' => $action,
                ],
            ];

            if ($direction === 'in') {
                $this->transactions->addStock((int) $item->inventory_item_id, (float) $item->quantity, $transactionPayload);
                continue;
            }

            $this->transactions->consume((int) $item->inventory_item_id, (float) $item->quantity, $transactionPayload);
        }
    }

    protected function generateDocumentNumber(string $type): string
    {
        $prefixMap = [
            'purchase_request' => 'PR',
            'purchase_order' => 'PO',
            'goods_receiving_note' => 'GRN',
            'production_order' => 'PROD',
            'store_voucher' => 'SV',
            'finished_goods_transfer' => 'FGT',
            'sales_order' => 'SO',
            'delivery_note' => 'DN',
            'dispatch' => 'DSP',
            'goods_return_note' => 'GR',
            'sales_summary' => 'SS',
            'waste_voucher' => 'WV',
        ];

        $prefix = $prefixMap[$type] ?? 'DOC';
        $datePart = now()->format('Ymd');
        $randomPart = Str::upper(Str::random(6));

        return "{$prefix}-{$datePart}-{$randomPart}";
    }

    protected function validateQualityCompliance(InventoryDocument $document, string $action): void
    {
        // Only run for water bottling business type
        // Use a generic way to check business type if available
        $businessType = property_exists(auth()->user(), 'tenant') ? auth()->user()->tenant->business_type : null;
        
        // If we can't determine business type from auth, try current_tenant() helper if it exists
        if (!$businessType && function_exists('current_tenant')) {
            $businessType = current_tenant()?->business_type;
        }

        if ($businessType !== 'water-bottling') {
            return;
        }

        // Only enforce on critical transitions that release stock to customers
        if (!in_array($action, ['approve', 'dispatch', 'complete'], true)) {
            return;
        }

        // Only for documents that involve outgoing finished goods
        if (!in_array($document->type, ['delivery_note', 'dispatch', 'finished_goods_transfer'], true)) {
            return;
        }

        $service = app(\Modules\Inventory\Services\QualityComplianceService::class);
        
        foreach ($document->items as $item) {
            // Check if batch is specified in metadata
            $batchId = $item->metadata['batch_id'] ?? null;
            if ($batchId) {
                if (!$service->isBatchReleasable((int) $batchId)) {
                    throw ValidationException::withMessages([
                        'quality' => "Batch #{$batchId} for '{$item->inventoryItem->name}' has not passed Quality Assurance and cannot be released/dispatched. Please perform mandatory tests and ensure results are within acceptable ranges.",
                    ]);
                }
            } else {
                // If the item belongs to a category that REQUIRES QA but no batch is assigned
                // We might want to warn or block, but for now we look for batch-specific QA.
            }
        }
    }
}
