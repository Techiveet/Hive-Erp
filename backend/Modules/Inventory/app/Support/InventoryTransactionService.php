<?php

namespace Modules\Inventory\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;

class InventoryTransactionService
{
    public function addStock(InventoryItem|int $item, float $quantity, array $payload = []): InventoryTransaction
    {
        return $this->record($item, 'in', $quantity, $payload);
    }

    public function consume(InventoryItem|int $item, float $quantity, array $payload = []): InventoryTransaction
    {
        return $this->record($item, 'out', $quantity, $payload);
    }

    public function adjust(InventoryItem|int $item, float $deltaQuantity, array $payload = []): InventoryTransaction
    {
        if ($deltaQuantity === 0.0) {
            throw ValidationException::withMessages([
                'quantity' => 'Adjustment quantity cannot be zero.',
            ]);
        }

        if ($deltaQuantity > 0) {
            return $this->addStock($item, abs($deltaQuantity), array_merge(['type' => 'manual_adjustment_in'], $payload));
        }

        return $this->consume($item, abs($deltaQuantity), array_merge(['type' => 'manual_adjustment_out'], $payload));
    }

    protected function record(InventoryItem|int $item, string $direction, float $quantity, array $payload = []): InventoryTransaction
    {
        $quantity = round(abs($quantity), 3);

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be greater than zero.',
            ]);
        }

        if (!in_array($direction, ['in', 'out'], true)) {
            throw ValidationException::withMessages([
                'direction' => 'Direction must be either in or out.',
            ]);
        }

        $itemId = $item instanceof InventoryItem ? $item->id : $item;

        return DB::transaction(function () use ($itemId, $direction, $quantity, $payload): InventoryTransaction {
            /** @var InventoryItem|null $lockedItem */
            $lockedItem = InventoryItem::query()->whereKey($itemId)->lockForUpdate()->first();

            if (!$lockedItem) {
                throw ValidationException::withMessages([
                    'item_id' => 'Inventory item not found.',
                ]);
            }

            $currentStock = (float) $lockedItem->current_stock;
            $delta = $direction === 'in' ? $quantity : -$quantity;
            $newBalance = round($currentStock + $delta, 3);

            if ($newBalance < 0) {
                throw ValidationException::withMessages([
                    'quantity' => "Insufficient stock for {$lockedItem->name}. Current stock is {$currentStock}.",
                ]);
            }

            $lockedItem->update([
                'current_stock' => $newBalance,
            ]);

            $unitCost = isset($payload['unit_cost']) ? (float) $payload['unit_cost'] : null;
            $totalCost = $unitCost !== null ? round($unitCost * $quantity, 2) : null;

            return InventoryTransaction::query()->create([
                'item_id' => $lockedItem->id,
                'direction' => $direction,
                'type' => (string) ($payload['type'] ?? 'manual_adjustment'),
                'quantity' => $quantity,
                'balance_after' => $newBalance,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'module_source' => $payload['module_source'] ?? 'inventory_control',
                'reference_type' => $payload['reference_type'] ?? null,
                'reference_id' => isset($payload['reference_id']) ? (string) $payload['reference_id'] : null,
                'notes' => $payload['notes'] ?? null,
                'performed_by_id' => $payload['performed_by_id'] ?? auth()->id(),
                'metadata' => is_array($payload['metadata'] ?? null) ? $payload['metadata'] : null,
            ]);
        });
    }
}
