<?php

namespace Modules\Inventory\Support;

use Modules\Inventory\Contracts\InventoryIntegrationGateway;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;

class EloquentInventoryIntegrationGateway implements InventoryIntegrationGateway
{
    public function __construct(
        private readonly InventoryTransactionService $inventoryTransactions
    ) {
    }

    public function getItemSnapshots(array $inventoryItemIds): array
    {
        $ids = $this->normalizeIds($inventoryItemIds);

        if ($ids === []) {
            return [];
        }

        return InventoryItem::query()
            ->select(['id', 'name', 'unit', 'current_stock', 'selling_price'])
            ->whereKey($ids)
            ->get()
            ->mapWithKeys(fn (InventoryItem $item): array => [
                (int) $item->id => $this->itemSnapshot($item),
            ])
            ->all();
    }

    public function getTransactionSnapshots(array $inventoryTransactionIds): array
    {
        $ids = $this->normalizeIds($inventoryTransactionIds);

        if ($ids === []) {
            return [];
        }

        return InventoryTransaction::query()
            ->select([
                'id',
                'item_id',
                'type',
                'direction',
                'quantity',
                'balance_after',
                'module_source',
                'reference_type',
                'reference_id',
            ])
            ->whereKey($ids)
            ->get()
            ->mapWithKeys(fn (InventoryTransaction $transaction): array => [
                (int) $transaction->id => $this->transactionSnapshot($transaction),
            ])
            ->all();
    }

    public function consume(int $inventoryItemId, float $quantity, array $payload = []): array
    {
        $transaction = $this->inventoryTransactions->consume($inventoryItemId, $quantity, $payload);

        return $this->transactionSnapshot($transaction);
    }

    /**
     * @param  array<int, int|string|null>  $ids
     * @return array<int, int>
     */
    private function normalizeIds(array $ids): array
    {
        return collect($ids)
            ->filter(fn ($id): bool => $id !== null && $id !== '')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function itemSnapshot(InventoryItem $item): array
    {
        return [
            'id' => (int) $item->id,
            'name' => (string) $item->name,
            'unit' => $item->unit,
            'current_stock' => (string) $item->current_stock,
            'selling_price' => (string) $item->selling_price,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionSnapshot(InventoryTransaction $transaction): array
    {
        return [
            'id' => (int) $transaction->id,
            'item_id' => (int) $transaction->item_id,
            'type' => (string) $transaction->type,
            'direction' => (string) $transaction->direction,
            'quantity' => (string) $transaction->quantity,
            'balance_after' => (string) $transaction->balance_after,
            'module_source' => $transaction->module_source,
            'reference_type' => $transaction->reference_type,
            'reference_id' => $transaction->reference_id,
        ];
    }
}
